<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use App\Notifications\CalendarEventInviteNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class CalendarEventController extends Controller
{
    public function index(int $projectId, Request $request): JsonResponse
    {
        $query = CalendarEvent::where('project_id', $projectId)
            ->with('creator:id,first_name,last_name');

        if ($request->start && $request->end) {
            $query->forDateRange($request->start, $request->end);
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%"));
        }

        $events = $query->orderBy('start_datetime')->get();

        $users = User::whereIn('id', $events->pluck('attendees')->flatten()->unique()->filter())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');
        $events->each(fn ($e) => $e->setAttribute(
            'attendee_users',
            collect($e->attendees ?? [])->map(fn ($id) => $users->get($id))->filter()->values()
        ));

        return $this->success($events);
    }

    public function store(int $projectId, Request $request): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:meeting,inspection,deadline,milestone,other'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['integer', Rule::exists('project_members', 'user_id')->where('project_id', $projectId)],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
        ]);

        $validated['project_id'] = $project->id;
        $validated['created_by'] = $request->user()->id;

        $event = CalendarEvent::create($validated);

        $this->notifyAttendees($event, $validated['attendees'] ?? [], $request->user());

        $event->load('creator:id,first_name,last_name')->setAttribute('attendee_users', $this->attendeeUsers($event));

        return $this->created($event, 'Event created.');
    }

    public function show(int $projectId, int $eventId): JsonResponse
    {
        $event = CalendarEvent::where('project_id', $projectId)
            ->with('creator:id,first_name,last_name')
            ->findOrFail($eventId);

        $event->setAttribute('attendee_users', $this->attendeeUsers($event));

        return $this->success($event);
    }

    public function update(int $projectId, int $eventId, Request $request): JsonResponse
    {
        $event = CalendarEvent::where('project_id', $projectId)->findOrFail($eventId);

        $oldAttendees = $event->attendees ?? [];

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:meeting,inspection,deadline,milestone,other'],
            'start_datetime' => ['sometimes', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['integer', Rule::exists('project_members', 'user_id')->where('project_id', $projectId)],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
        ]);

        $event->update($validated);
        $event = $event->fresh();

        if (array_key_exists('attendees', $validated)) {
            $newAttendees = $validated['attendees'] ?? [];
            $added = array_diff($newAttendees, $oldAttendees);

            if (! empty($added)) {
                $this->notifyAttendees($event, array_values($added), $request->user());
            }
        }

        $event->load('creator:id,first_name,last_name')->setAttribute('attendee_users', $this->attendeeUsers($event));

        return $this->success($event, 'Event updated.');
    }

    public function destroy(int $projectId, int $eventId): JsonResponse
    {
        $event = CalendarEvent::where('project_id', $projectId)->findOrFail($eventId);
        $event->delete();

        return $this->success(null, 'Event deleted.');
    }

    private function notifyAttendees(CalendarEvent $event, array $userIds, User $actor): void
    {
        $ids = collect($userIds)->reject(fn ($id) => (int) $id === $actor->id)->unique()->values()->all();

        if (empty($ids)) {
            return;
        }

        try {
            $event->loadMissing('project');

            $recipients = User::whereIn('id', $ids)->get();

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send(
                $recipients,
                new CalendarEventInviteNotification($event, $event->project->name, trim("{$actor->first_name} {$actor->last_name}"))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send calendar event attendee invitations', [
                'event_id' => $event->id,
                'recipient_ids' => $ids,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function attendeeUsers(CalendarEvent $event): \Illuminate\Support\Collection
    {
        return User::whereIn('id', $event->attendees ?? [])->get(['id', 'first_name', 'last_name']);
    }
}
