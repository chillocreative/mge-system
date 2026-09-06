<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlementRule extends Model
{
    protected $fillable = [
        'leave_type_id', 'min_years', 'max_years', 'days', 'staff_category',
        'employment_type', 'effective_from', 'is_seed_default', 'is_active', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'min_years' => 'decimal:2',
            'max_years' => 'decimal:2',
            'days' => 'decimal:2',
            'effective_from' => 'date',
            'is_seed_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
