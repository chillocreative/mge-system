<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkPattern extends Model
{
    protected $fillable = [
        'name', 'working_days', 'is_default_for', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
