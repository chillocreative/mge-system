<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveClients(int $perPage = 15): LengthAwarePaginator;

    public function searchClients(string $search, int $perPage = 15): LengthAwarePaginator;
}
