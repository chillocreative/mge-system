<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'budget' => $this->budget,
            'spent' => $this->spent,
            'progress' => $this->progress,
            'location' => $this->location,
            'notes' => $this->notes,
            'is_overdue' => $this->isOverdue(),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'company_name' => $this->client->company_name,
            ]),
            'manager' => $this->whenLoaded('manager', fn () => new UserResource($this->manager)),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'tasks' => $this->whenLoaded('tasks'),
            'milestones' => $this->whenLoaded('milestones'),
            'site_logs' => $this->whenLoaded('siteLogs'),
            'documents' => $this->whenLoaded('documents'),
            'calendar_events' => $this->whenLoaded('calendarEvents'),
            'tasks_count' => $this->whenCounted('tasks'),
            'milestones_count' => $this->whenCounted('milestones'),
            'site_logs_count' => $this->whenCounted('siteLogs'),
            'documents_count' => $this->whenCounted('documents'),
            'calendar_events_count' => $this->whenCounted('calendarEvents'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
