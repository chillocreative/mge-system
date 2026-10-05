<?php

namespace App\Repositories\Eloquent;

use App\Models\Material;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MaterialRepository implements MaterialRepositoryInterface
{
    public function list(?string $search, ?string $category, bool $activeOnly): Collection
    {
        return Material::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($search, fn ($query) => $query->where(fn ($q) => $q->where('category', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->orderBy('category')->orderBy('description')->get();
    }

    public function find(int $id): Material
    {
        return Material::findOrFail($id);
    }

    public function create(array $data): Material
    {
        return Material::create($data);
    }

    public function update(Material $material, array $data): Material
    {
        $material->update($data);

        return $material->refresh();
    }

    public function pairExists(string $category, string $description, ?int $exceptId = null): bool
    {
        return Material::query()->where('category', $category)->where('description', $description)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists();
    }

    public function activePairExists(string $category, string $description): bool
    {
        return Material::query()->where('category', $category)->where('description', $description)->where('is_active', true)->exists();
    }
}
