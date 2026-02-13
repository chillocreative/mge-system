<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toDateString(),
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'sort_order' => $this->sort_order,
            'is_overdue' => $this->isOverdue(),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => new UserResource($this->assignee)),
            'creator' => $this->whenLoaded('creator', fn () => new UserResource($this->creator)),
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'title' => $this->parent->title,
            ]),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'attachments_count' => $this->whenCounted('attachments'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
