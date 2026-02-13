<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface TaskRepositoryInterface extends BaseRepositoryInterface
{
    public function getByProject(int $projectId, array $filters = []): LengthAwarePaginator;

    public function getByAssignee(int $userId, array $filters = []): LengthAwarePaginator;

    public function getOverdueTasks(): Collection;
}
