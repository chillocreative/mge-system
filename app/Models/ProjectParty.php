<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A party on a project (Batch 7). Parties are data, not a fixed set of columns,
 * so a project can carry as many as it actually has.
 */
class ProjectParty extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'name', 'type', 'contact_person', 'email', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
