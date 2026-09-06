<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveQuotaPool extends Model
{
    protected $fillable = [
        'name', 'cap_days', 'cap_source', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cap_days' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class, 'quota_pool_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
