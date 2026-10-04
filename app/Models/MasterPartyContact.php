<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterPartyContact extends Model
{
    protected $fillable = ['master_party_id', 'contact_type', 'name', 'position', 'phone', 'email'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(MasterParty::class, 'master_party_id');
    }
}
