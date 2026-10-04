<?php

namespace App\Services;

use App\Models\PartyCategory;
use App\Repositories\Contracts\PartyCategoryRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartyCategoryService
{
    public function __construct(private readonly PartyCategoryRepositoryInterface $repository) {}

    public function list(bool $activeOnly = false): Collection
    {
        return $this->repository->all($activeOnly);
    }

    public function create(array $data): PartyCategory
    {
        return $this->repository->create($this->attributes($data));
    }

    public function update(int $id, array $data): PartyCategory
    {
        $category = $this->repository->findOrFail($id);
        $category->update($this->attributes($data, $category));

        return $category->refresh()->loadCount('parties');
    }

    public function delete(int $id): void
    {
        $category = $this->repository->findOrFail($id);
        if ($category->is_system) {
            throw ValidationException::withMessages(['category' => 'System categories cannot be deleted.']);
        }
        if ($category->parties()->exists()) {
            throw ValidationException::withMessages(['category' => 'This category is assigned to parties and cannot be deleted.']);
        }
        $category->delete();
    }

    private function attributes(array $data, ?PartyCategory $category = null): array
    {
        return [
            'name' => trim($data['name']),
            'slug' => $category?->is_system ? $category->slug : Str::slug($data['slug'] ?? $data['name'], '_'),
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }
}
