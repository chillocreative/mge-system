<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface extends BaseRepositoryInterface
{
    public function getWithRelations(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    public function getByManager(int $managerId): Collection;

    public function getByClient(int $clientId): Collection;

    public function getActiveProjects(): Collection;
}
