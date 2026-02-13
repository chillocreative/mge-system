<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $events = $query->orderBy('start_datetime')->get();

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
            'attendees.*' => ['exists:users,id'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
        ]);

        $validated['project_id'] = $project->id;
        $validated['created_by'] = $request->user()->id;

        $event = CalendarEvent::create($validated);

        return $this->created($event->load('creator:id,first_name,last_name'), 'Event created.');
    }

    public function show(int $projectId, int $eventId): JsonResponse
    {
        $event = CalendarEvent::where('project_id', $projectId)
            ->with('creator:id,first_name,last_name')
            ->findOrFail($eventId);

        return $this->success($event);
    }

    public function update(int $projectId, int $eventId, Request $request): JsonResponse
    {
        $event = CalendarEvent::where('project_id', $projectId)->findOrFail($eventId);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:meeting,inspection,deadline,milestone,other'],
            'start_datetime' => ['sometimes', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['exists:users,id'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
        ]);

        $event->update($validated);

        return $this->success($event->fresh()->load('creator:id,first_name,last_name'), 'Event updated.');
    }

    public function destroy(int $projectId, int $eventId): JsonResponse
    {
        $event = CalendarEvent::where('project_id', $projectId)->findOrFail($eventId);
        $event->delete();

        return $this->success(null, 'Event deleted.');
    }
}
