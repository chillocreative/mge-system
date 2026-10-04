<?php

namespace App\Services;

use App\Models\Client;
use App\Models\MasterParty;
use App\Models\PartyCategory;
use App\Repositories\Contracts\MasterPartyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MasterPartyService
{
    public function __construct(private readonly MasterPartyRepositoryInterface $repository) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, min(max($perPage, 1), 100));
    }

    public function options(?string $search, ?string $category, int $limit = 20): Collection
    {
        return $this->repository->options($search, $category, $limit);
    }

    public function get(int $id): MasterParty
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): MasterParty
    {
        return DB::transaction(function () use ($data) {
            $party = $this->repository->create($this->partyAttributes($data));
            $this->syncCategoriesAndContacts($party, $data);
            $this->syncCompatibilityRecords($party);

            return $this->get($party->id);
        });
    }

    public function update(int $id, array $data): MasterParty
    {
        return DB::transaction(function () use ($id, $data) {
            $party = $this->repository->findOrFail($id);
            $clientCategoryId = PartyCategory::where('slug', 'client')->value('id');
            if ($party->legacyClients()->exists() && ! in_array((int) $clientCategoryId, array_map('intval', $data['category_ids']), true)) {
                throw ValidationException::withMessages([
                    'category_ids' => 'The Client category cannot be removed while legacy client records reference this party.',
                ]);
            }

            $party->update($this->partyAttributes($data));
            $this->syncCategoriesAndContacts($party, $data);
            $this->syncCompatibilityRecords($party);

            return $this->get($party->id);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $party = $this->repository->findOrFail($id);
            $references = [
                'clients' => $party->legacyClients()->count(),
                'project parties' => $party->projectParties()->count(),
                'project contracts' => $party->projectContracts()->count(),
            ];
            $usedBy = collect($references)->filter()->keys();
            if ($usedBy->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'party' => 'This party is referenced by '.implode(', ', $usedBy->all()).' and cannot be deleted.',
                ]);
            }
            $party->delete();
        });
    }

    public static function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->lower()->replaceMatches('/\s+/', ' ')->toString();
    }

    private function partyAttributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'normalized_name' => self::normalizeName($data['name']),
            'initial' => strtoupper($data['initial']),
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'country' => $data['country'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'website' => $data['website'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    private function syncCategoriesAndContacts(MasterParty $party, array $data): void
    {
        $party->categories()->sync($data['category_ids']);
        foreach (['main', 'additional'] as $type) {
            $party->contacts()->updateOrCreate(
                ['contact_type' => $type],
                $data['contacts'][$type] + ['contact_type' => $type],
            );
        }
    }

    private function syncCompatibilityRecords(MasterParty $party): void
    {
        $party->load(['categories', 'contacts']);
        $main = $party->contacts->firstWhere('contact_type', 'main');

        if ($party->categories->contains('slug', 'client')) {
            $emailOwner = Client::where('email', $main->email)
                ->where(fn ($query) => $query->whereNull('master_party_id')->orWhere('master_party_id', '!=', $party->id))
                ->first();
            if ($emailOwner) {
                throw ValidationException::withMessages([
                    'contacts.main.email' => 'This email is already used by another legacy client record.',
                ]);
            }

            Client::updateOrCreate(
                ['master_party_id' => $party->id],
                [
                    'company_name' => $party->name,
                    'contact_person' => $main->name,
                    'email' => $main->email,
                    'phone' => $main->phone,
                    'address' => $party->address,
                    'city' => $party->city,
                    'state' => $party->state,
                    'country' => $party->country,
                    'zip_code' => $party->postcode,
                    'website' => $party->website,
                    'status' => $party->is_active ? 'active' : 'inactive',
                ],
            );
        }

        $party->legacyClients()->update([
            'company_name' => $party->name,
            'contact_person' => $main->name,
            'email' => $main->email,
            'phone' => $main->phone,
            'address' => $party->address,
            'city' => $party->city,
            'state' => $party->state,
            'country' => $party->country,
            'zip_code' => $party->postcode,
            'website' => $party->website,
            'status' => $party->is_active ? 'active' : 'inactive',
        ]);
        $party->projectParties()->update([
            'name' => $party->name,
            'contact_person' => $main->name,
            'email' => $main->email,
            'phone' => $main->phone,
            'address' => $party->address,
            'is_active' => $party->is_active,
        ]);
    }
}
