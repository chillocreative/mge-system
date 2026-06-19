<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskService
{
    private const TASK_RELATIONS = ['project', 'assignee', 'assignees', 'creator', 'attachments.uploader'];

    public function getProjectTasks(int $projectId, array $filters, User $user): LengthAwarePaginator
    {
        $query = Task::where('project_id', $projectId)
            ->with(['assignee', 'assignees', 'creator']);

        // #6 — non-managers only see tasks assigned to them.
        if (! $user->can('tasks.edit')) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn ($a) => $a->where('users.id', $user->id))
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $this->applyFilters($query, $filters);

        return $query->orderBy('sort_order')->latest()->paginate($this->perPage($filters));
    }

    public function getMyTasks(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Task::with(['project', 'assignees', 'creator'])
            ->where(function ($q) use ($userId) {
                $q->whereHas('assignees', fn ($a) => $a->where('users.id', $userId))
                    ->orWhere('assigned_to', $userId);
            });

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($this->perPage($filters));
    }

    public function getTask(int $id): Task
    {
        return Task::with([
            'project', 'assignee', 'assignees', 'creator',
            'comments.user', 'attachments.uploader', 'subtasks',
            'activities.user',
        ])->findOrFail($id);
    }

    public function createTask(array $data, array $assigneeIds = [], array $files = []): Task
    {
        return DB::transaction(function () use ($data, $assigneeIds, $files) {
            $data['created_by'] = auth()->id();
            $data['assigned_to'] = $assigneeIds[0] ?? ($data['assigned_to'] ?? null);

            $task = Task::create($data);

            if (! empty($assigneeIds)) {
                $task->assignees()->sync($assigneeIds);
            }

            $this->storeAttachments($task, $files);

            $this->logActivity($task, 'created', ['title' => $task->title]);

            return $this->getTask($task->id);
        });
    }

    public function updateTask(int $id, array $data, ?array $assigneeIds = null): Task
    {
        return DB::transaction(function () use ($id, $data, $assigneeIds) {
            $task = Task::findOrFail($id);
            $oldStatus = $task->status;

            if (isset($data['status']) && $data['status'] === 'completed') {
                $data['completed_at'] = now();
            }

            if ($assigneeIds !== null) {
                $data['assigned_to'] = $assigneeIds[0] ?? null;
            }

            $task->update($data);

            $logged = false;

            if ($assigneeIds !== null) {
                $task->assignees()->sync($assigneeIds);
                $this->logActivity($task, 'reassigned', ['count' => count($assigneeIds)]);
                $logged = true;
            }

            if (isset($data['status']) && $data['status'] !== $oldStatus) {
                $this->logActivity($task, 'status_changed', ['from' => $oldStatus, 'to' => $data['status']]);
                $logged = true;
            }

            if (! $logged) {
                $this->logActivity($task, 'updated');
            }

            return $this->getTask($task->id);
        });
    }

    public function deleteTask(int $id): bool
    {
        $task = Task::findOrFail($id);
        $this->logActivity($task, 'deleted', ['title' => $task->title]);

        return (bool) $task->delete();
    }

    public function downloadAttachment(int $attachmentId)
    {
        $attachment = TaskAttachment::findOrFail($attachmentId);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    // ── Helpers ──

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['assigned_to'])) {
            $query->whereHas('assignees', fn ($a) => $a->where('users.id', $filters['assigned_to']));
        }
    }

    private function perPage(array $filters): int
    {
        return min((int) ($filters['per_page'] ?? 15), 100);
    }

    private function storeAttachments(Task $task, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('tasks/attachments', 'local');
            $task->attachments()->create([
                'uploaded_by' => auth()->id(),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function logActivity(Task $task, string $action, array $properties = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'properties' => $properties,
            'ip_address' => request()->ip(),
        ]);
    }
}
