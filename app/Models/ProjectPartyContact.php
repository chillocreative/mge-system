<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPartyContact extends Model
{
    protected $fillable = ['project_party_id', 'name', 'designation', 'phone', 'email', 'sort_order'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(ProjectParty::class, 'project_party_id');
    }
}
