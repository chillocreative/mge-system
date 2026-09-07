<?php

namespace App\Services;

use App\Models\CompanyEvent;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CalendarService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Return all events within the given range (NOT paginated).
     *
     * @param  array{start?:string,end?:string,type?:string}  $filters
     */
    public function list(array $filters): Collection
    {
        $query = CompanyEvent::with([
            'creator:id,first_name,last_name',
            'employee:id,first_name,last_name',
            'attendees:id,first_name,last_name',
            'project:id,name',
        ])->orderBy('start_datetime');

        if (! empty($filters['start']) && ! empty($filters['end'])) {
            $query->forRange($filters['start'], $filters['end']);
        }

        if (! empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        return $query->get();
    }

    public function create(array $data, int $userId): CompanyEvent
    {
        $data['created_by'] = $userId;
        $data['source'] = $data['source'] ?? 'app';

        $attendeeIds = $this->pullAttendeeIds($data);
        $event = CompanyEvent::create($data);
        $this->syncAttendees($event, $attendeeIds, $userId, isNew: true);

        return $event->load([
            'creator:id,first_name,last_name',
            'employee:id,first_name,last_name',
            'attendees:id,first_name,last_name',
            'project:id,name',
        ]);
    }

    public function update(int $id, array $data): CompanyEvent
    {
        $attendeeIds = $this->pullAttendeeIds($data);
        $event = CompanyEvent::findOrFail($id);
        $event->update($data);
        if ($attendeeIds !== null) {
            $this->syncAttendees($event, $attendeeIds, $event->created_by, isNew: false);
        }

        return $event->load([
            'creator:id,first_name,last_name',
            'employee:id,first_name,last_name',
            'attendees:id,first_name,last_name',
            'project:id,name',
        ]);
    }

    public function delete(int $id): void
    {
        CompanyEvent::findOrFail($id)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>|null attendee employee ids, or null if the caller did not send the field
     */
    private function pullAttendeeIds(array &$data): ?array
    {
        if (! array_key_exists('attendee_ids', $data)) {
            return null;
        }
        $ids = collect($data['attendee_ids'] ?? [])->map(fn ($i) => (int) $i)->filter()->unique()->values()->all();
        unset($data['attendee_ids']);

        return $ids;
    }

    /**
     * Attach the staff on an event and tell them about it (Ciri 14). Notifying
     * only the newly-added attendees on an edit keeps a small change from
     * re-pinging everyone.
     */
    private function syncAttendees(CompanyEvent $event, ?array $attendeeIds, ?int $actorId, bool $isNew): void
    {
        if ($attendeeIds === null) {
            return;
        }

        $before = $isNew ? [] : $event->attendees()->pluck('employees.id')->all();
        $event->attendees()->sync($attendeeIds);
        $newlyAdded = array_diff($attendeeIds, $before);

        if ($newlyAdded === []) {
            return;
        }

        // Resolve attendee employees -> their login user ids, and never ping the
        // person creating the event.
        $userIds = Employee::whereIn('id', $newlyAdded)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->reject(fn ($id) => $id === $actorId)
            ->all();

        $when = Carbon::parse($event->start_datetime)->format('d M Y, g:i A');
        $this->notifications->notifyUserIds(
            $userIds,
            'Event: '.$event->title,
            "You have been added to \"{$event->title}\" on {$when}.",
            'event',
            '/hr/calendar',
            ['event_id' => $event->id],
            'event',
        );
    }

    /**
     * Aggregated HR calendar (Ciri 13): company events + approved leave + public
     * holidays merged into one feed for the range. The plan is emphatic that
     * this AGGREGATES rather than copies (13.1) — nothing is duplicated into
     * company_events; each source is read live.
     *
     * Privacy (decision, 7 Sep): leave is shown only to the person who applied
     * and to HR (leave.view). Everyone else does not see leave entries at all.
     *
     * @return array<int, array<string, mixed>>
     */
    public function aggregate(array $filters, User $viewer): array
    {
        $start = $filters['start'] ?? now()->startOfMonth()->toDateString();
        $end = $filters['end'] ?? now()->endOfMonth()->toDateString();

        $feed = [];

        // 1. Company events (with simple recurrence expansion within the range).
        foreach (CompanyEvent::forRange($start, $end)->orWhere('recurrence', '!=', 'none')->get() as $event) {
            foreach ($this->expandOccurrences($event, $start, $end) as $occ) {
                $feed[] = [
                    'source' => 'event',
                    'type' => $event->type,
                    'title' => $event->title,
                    'start' => $occ,
                    'all_day' => (bool) $event->all_day,
                    'ref_id' => $event->id,
                ];
            }
        }

        // 2. Approved leave — privacy-scoped.
        $canSeeAllLeave = $viewer->can('leave.view');
        $ownEmployeeId = Employee::where('user_id', $viewer->id)->value('id');

        $leaveQuery = LeaveRequest::with('employee:id,first_name,last_name', 'leaveType:id,name,code')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);

        if (! $canSeeAllLeave) {
            // Only their own leave, and nothing if they have no staff record.
            if (! $ownEmployeeId) {
                $leaveQuery->whereRaw('1 = 0');
            } else {
                $leaveQuery->where('employee_id', $ownEmployeeId);
            }
        }

        foreach ($leaveQuery->get() as $leave) {
            $isOwn = $ownEmployeeId && $leave->employee_id === $ownEmployeeId;
            $name = $leave->employee ? trim($leave->employee->first_name.' '.$leave->employee->last_name) : 'Staff';
            $feed[] = [
                'source' => 'leave',
                'type' => 'leave',
                // HR sees who + which type; the person sees their own detail.
                'title' => ($isOwn ? 'Your leave' : $name).' — '.($leave->leaveType->code ?? 'Leave'),
                'start' => (string) $leave->start_date,
                'end' => (string) $leave->end_date,
                'all_day' => true,
                'ref_id' => $leave->id,
            ];
        }

        // 3. Public holidays (national + the operating state).
        $state = config('leave.holiday_state');
        foreach (PublicHoliday::active()
            ->whereDate('date', '>=', $start)->whereDate('date', '<=', $end)
            ->where(fn ($q) => $q->where('scope', 'national')->orWhere(fn ($q2) => $q2->where('scope', 'state')->where('state', $state)))
            ->get() as $holiday) {
            $feed[] = [
                'source' => 'holiday',
                'type' => 'holiday',
                'title' => $holiday->name,
                'start' => $holiday->date->toDateString(),
                'all_day' => true,
                'ref_id' => $holiday->id,
            ];
        }

        usort($feed, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return $feed;
    }

    /**
     * Expand a possibly-recurring event into the occurrence start-dates that
     * fall within [start, end]. Bounded by the queried range and by
     * recurrence_until, so it can never run away.
     *
     * @return array<int, string>
     */
    private function expandOccurrences(CompanyEvent $event, string $start, string $end): array
    {
        $rangeStart = Carbon::parse($start)->startOfDay();
        $rangeEnd = Carbon::parse($end)->endOfDay();
        $cursor = Carbon::parse($event->start_datetime);
        $recurrence = $event->recurrence ?? 'none';

        if ($recurrence === 'none') {
            return ($cursor->betweenIncluded($rangeStart, $rangeEnd)) ? [$cursor->toDateTimeString()] : [];
        }

        $until = $event->recurrence_until ? Carbon::parse($event->recurrence_until)->endOfDay() : $rangeEnd;
        $stop = $until->lessThan($rangeEnd) ? $until : $rangeEnd;

        $occurrences = [];
        $guard = 0;
        while ($cursor->lessThanOrEqualTo($stop) && $guard < 500) {
            if ($cursor->greaterThanOrEqualTo($rangeStart)) {
                $occurrences[] = $cursor->toDateTimeString();
            }
            $cursor = match ($recurrence) {
                'daily' => $cursor->copy()->addDay(),
                'weekly' => $cursor->copy()->addWeek(),
                'monthly' => $cursor->copy()->addMonth(),
                default => $stop->copy()->addDay(),
            };
            $guard++;
        }

        return $occurrences;
    }
}
