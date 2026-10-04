<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectReferenceTemplate extends Model
{
    protected $fillable = [
        'project_id', 'code', 'name', 'type_token', 'pattern', 'padding',
        'reset_period', 'is_active',
    ];

    protected function casts(): array
    {
        return ['padding' => 'integer', 'is_active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(ProjectReferenceSequence::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProjectReferenceAllocation::class);
    }
}
