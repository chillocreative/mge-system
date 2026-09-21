<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    public function __construct(Project $model)
    {
        parent::__construct($model);
    }

    public function getWithRelations(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['client', 'manager', 'contracts:id,project_id,contract_no'])->withCount('documents');

        if (! empty($filters['status']) && $filters['status'] === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');

            if (! empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }
        }

        if (! empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getByManager(int $managerId): Collection
    {
        return $this->model->where('manager_id', $managerId)->active()->get();
    }

    public function getByClient(int $clientId): Collection
    {
        return $this->model->where('client_id', $clientId)->get();
    }

    public function getActiveProjects(): Collection
    {
        return $this->model->active()->with(['client', 'manager'])->get();
    }

    public function archive(int $id): Project
    {
        $project = $this->model->findOrFail($id);
        // archived_at is deliberately excluded from $fillable (never settable
        // via mass-assignment on create/update), so it needs forceFill() here.
        $project->forceFill(['archived_at' => now()])->save();

        return $project->fresh();
    }

    public function unarchive(int $id): Project
    {
        $project = $this->model->findOrFail($id);
        $project->forceFill(['archived_at' => null])->save();

        return $project->fresh();
    }
}
