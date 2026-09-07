<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physical site/zone within a project (Ciri 25). Optional structured refinement
 * over the free-text location every module already carries.
 */
class ProjectSite extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'name', 'code', 'address', 'latitude', 'longitude', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
