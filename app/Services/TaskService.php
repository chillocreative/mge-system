<?php

namespace App\Services;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    public function getProjectTasks(int $projectId, array $filters = []): LengthAwarePaginator
    {
        return $this->taskRepository->getByProject($projectId, $filters);
    }

    public function getMyTasks(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->taskRepository->getByAssignee($userId, $filters);
    }

    public function getTask(int $id): Task
    {
        $task = $this->taskRepository->findOrFail($id);
        $task->load(['project', 'assignee', 'creator', 'comments.user', 'attachments', 'subtasks']);

        return $task;
    }

    public function createTask(array $data): Task
    {
        $data['created_by'] = auth()->id();

        $task = $this->taskRepository->create($data);

        return $task->load(['project', 'assignee', 'creator']);
    }

    public function updateTask(int $id, array $data): Task
    {
        if (isset($data['status']) && $data['status'] === 'completed') {
            $data['completed_at'] = now();
        }

        $task = $this->taskRepository->update($id, $data);

        return $task->load(['project', 'assignee', 'creator']);
    }

    public function deleteTask(int $id): bool
    {
        return $this->taskRepository->delete($id);
    }
}
