<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartyCategory extends Model
{
    protected $fillable = ['name', 'slug', 'is_system', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(MasterParty::class, 'master_party_category');
    }
}
