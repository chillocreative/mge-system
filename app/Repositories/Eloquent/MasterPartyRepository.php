<?php

namespace App\Repositories\Eloquent;

use App\Models\MasterParty;
use App\Repositories\Contracts\MasterPartyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MasterPartyRepository implements MasterPartyRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return MasterParty::query()
            ->with(['categories', 'contacts'])
            ->withCount(['legacyClients', 'projectParties', 'projectContracts'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query
                ->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('initial', 'like', "%{$search}%")))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query
                ->whereHas('categories', fn ($nested) => $nested->where('slug', $category)))
            ->when(array_key_exists('active', $filters), fn ($query) => $query->where('is_active', $filters['active']))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function options(?string $search = null, ?string $category = null, int $limit = 20): Collection
    {
        return MasterParty::query()
            ->select(['id', 'name'])
            ->where('is_active', true)
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($category, fn ($query) => $query
                ->whereHas('categories', fn ($nested) => $nested->where('slug', $category)->where('is_active', true)))
            ->orderBy('name')
            ->limit(min(max($limit, 1), 100))
            ->get();
    }

    public function findOrFail(int $id): MasterParty
    {
        return MasterParty::with(['categories', 'contacts'])->findOrFail($id);
    }

    public function create(array $data): MasterParty
    {
        return MasterParty::create($data);
    }
}
