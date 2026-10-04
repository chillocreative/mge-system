<?php

namespace App\Repositories\Contracts;

use App\Models\PartyCategory;
use Illuminate\Support\Collection;

interface PartyCategoryRepositoryInterface
{
    public function all(bool $activeOnly = false): Collection;

    public function findOrFail(int $id): PartyCategory;

    public function create(array $data): PartyCategory;
}
