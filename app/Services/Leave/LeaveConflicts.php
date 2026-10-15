<?php

namespace App\Services\Leave;

use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Services\CalendarService;

/**
 * What else is on the calendar during the dates a leave request asks for, so the head / project
 * manager can check for meetings, training or a thin team before signing. Read-only.
 */
class LeaveConflicts
{
    public function __construct(private readonly CalendarService $calendar) {}

    /**
     * @return array<int, array{kind: string, title: string, start: string, end: ?string}>
     */
    public function forRequest(LeaveRequest $request, User $viewer): array
    {
        $employee = $request->employee ?? Employee::find($request->employee_id);
        $start = $request->start_date->toDateString();
        $end = $request->end_date->toDateString();
        $items = [];

        // Company events and public holidays (the shared feed also carries leave and training; those are scoped below).
        foreach ($this->calendar->aggregate(['start' => $start, 'end' => $end], $viewer) as $entry) {
            if (in_array($entry['source'], ['event', 'holiday'], true)) {
                $items[] = [
                    'kind' => $entry['source'] === 'holiday' ? 'holiday' : 'event',
                    'title' => $entry['title'],
                    'start' => substr((string) $entry['start'], 0, 10),
                    'end' => isset($entry['end']) ? substr((string) $entry['end'], 0, 10) : null,
                ];
            }
        }

        // The employee's own training in that window.
        foreach (TrainingRecord::where('employee_id', $request->employee_id)
            ->whereDate('training_date', '<=', $end)
            ->where(fn ($q) => $q->whereDate('training_date', '>=', $start)->orWhereDate('end_date', '>=', $start))
            ->get() as $training) {
            $items[] = ['kind' => 'training', 'title' => $training->title, 'start' => $training->training_date->toDateString(), 'end' => $training->end_date?->toDateString()];
        }

        // Project meetings / inspections the employee is invited to or part of.
        if ($employee?->user_id) {
            $projectIds = User::find($employee->user_id)?->projects()->pluck('projects.id') ?? collect();

            foreach (CalendarEvent::whereIn('project_id', $projectIds)
                ->where('status', '!=', 'cancelled')
                ->forDateRange($start.' 00:00:00', $end.' 23:59:59')
                ->get() as $event) {
                $attendees = (array) $event->attendees;

                if ($attendees === [] || in_array((int) $employee->user_id, array_map('intval', $attendees), true)) {
                    $items[] = [
                        'kind' => 'project_event',
                        'title' => $event->title,
                        'start' => $event->start_datetime->toDateString(),
                        'end' => $event->end_datetime?->toDateString(),
                    ];
                }
            }
        }

        // Colleagues in the same department who are already off.
        $department = $employee?->department_id;
        if ($department) {
            foreach (LeaveRequest::with('employee:id,first_name,last_name')
                ->whereIn('status', ['pending', 'approved'])
                ->where('id', '!=', $request->id)
                ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
                ->whereHas('employee', fn ($q) => $q->where('department_id', $department)->where('id', '!=', $request->employee_id))
                ->limit(10)->get() as $other) {
                $items[] = [
                    'kind' => 'team_leave',
                    'title' => trim($other->employee->first_name.' '.$other->employee->last_name).' ('.$other->status.')',
                    'start' => $other->start_date->toDateString(),
                    'end' => $other->end_date->toDateString(),
                ];
            }
        }

        usort($items, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return $items;
    }
}
