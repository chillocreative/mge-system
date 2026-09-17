<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A party on a project (Batch 7). Parties are data, not a fixed set of columns,
 * so a project can carry as many as it actually has.
 */
class ProjectParty extends Model
{
    use HasFactory;

    public const REPORT_ROLES = ['owner', 'superintending_officer', 'so_representative', 'district_engineer', 'quantity_surveyor', 'consultant', 'contractor', 'other'];

    protected $fillable = ['project_id', 'name', 'type', 'contact_person', 'email', 'phone', 'is_active', 'report_role', 'role_label', 'address', 'logo_path', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ProjectPartyContact::class)->orderBy('sort_order');
    }
}
