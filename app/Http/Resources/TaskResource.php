<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    private static function describeActivity($log): string
    {
        $p = $log->properties ?? [];

        return match ($log->action) {
            'created' => 'created this task',
            'updated' => 'updated the task',
            'status_changed' => 'changed status from ' . str_replace('_', ' ', $p['from'] ?? '?') . ' to ' . str_replace('_', ' ', $p['to'] ?? '?'),
            'reassigned' => 'updated the assignees',
            'attachment_added' => 'added an attachment',
            'deleted' => 'deleted the task',
            default => str_replace('_', ' ', $log->action),
        };
    }

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
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'creator' => $this->whenLoaded('creator', fn () => new UserResource($this->creator)),
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'title' => $this->parent->title,
            ]),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'attachments_count' => $this->whenCounted('attachments'),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_size' => $a->file_size,
                'file_type' => $a->file_type,
                'uploaded_by' => $a->uploader?->full_name,
                'download_url' => "/api/tasks/attachments/{$a->id}/download",
                'created_at' => $a->created_at?->toISOString(),
            ])),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => self::describeActivity($log),
                'user' => $log->user?->full_name ?? 'System',
                'created_at' => $log->created_at?->toISOString(),
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
