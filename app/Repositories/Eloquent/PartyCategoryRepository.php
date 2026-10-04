<?php

namespace App\Repositories\Eloquent;

use App\Models\PartyCategory;
use App\Repositories\Contracts\PartyCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class PartyCategoryRepository implements PartyCategoryRepositoryInterface
{
    public function all(bool $activeOnly = false): Collection
    {
        return PartyCategory::query()
            ->withCount('parties')
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function findOrFail(int $id): PartyCategory
    {
        return PartyCategory::withCount('parties')->findOrFail($id);
    }

    public function create(array $data): PartyCategory
    {
        return PartyCategory::create($data);
    }
}
