<?php

namespace App\Repositories\Contracts;

use App\Models\MasterParty;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MasterPartyRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function options(?string $search = null, ?string $category = null, int $limit = 20): Collection;

    public function findOrFail(int $id): MasterParty;

    public function create(array $data): MasterParty;
}
