<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterParty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'normalized_name', 'initial', 'address', 'city', 'state', 'country',
        'postcode', 'website', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PartyCategory::class, 'master_party_category')
            ->orderBy('sort_order')->orderBy('name');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(MasterPartyContact::class)
            ->orderByRaw("CASE WHEN contact_type = 'main' THEN 0 ELSE 1 END");
    }

    public function legacyClients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projectParties(): HasMany
    {
        return $this->hasMany(ProjectParty::class);
    }

    public function projectContracts(): HasMany
    {
        return $this->hasMany(ProjectContract::class);
    }
}
