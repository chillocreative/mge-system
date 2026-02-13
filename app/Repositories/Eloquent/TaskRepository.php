<?php

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskRepository extends BaseRepository implements TaskRepositoryInterface
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    public function getByProject(int $projectId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->where('project_id', $projectId)
            ->with(['assignee', 'creator']);

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query->orderBy('sort_order')->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function getByAssignee(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->where('assigned_to', $userId)
            ->with(['project', 'creator']);

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function getOverdueTasks(): Collection
    {
        return $this->model->overdue()
            ->with(['project', 'assignee'])
            ->get();
    }
}
