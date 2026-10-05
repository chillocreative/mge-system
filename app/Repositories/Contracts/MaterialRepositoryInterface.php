<?php

namespace App\Repositories\Contracts;

use App\Models\Material;
use Illuminate\Database\Eloquent\Collection;

interface MaterialRepositoryInterface
{
    public function list(?string $search, ?string $category, bool $activeOnly): Collection;

    public function find(int $id): Material;

    public function create(array $data): Material;

    public function update(Material $material, array $data): Material;

    public function pairExists(string $category, string $description, ?int $exceptId = null): bool;

    public function activePairExists(string $category, string $description): bool;
}
