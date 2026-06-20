<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'name', 'code', 'default_days_per_year', 'is_paid', 'requires_attachment', 'is_active',
        'requires_director_approval', 'manager_approver_id', 'director_approver_id',
    ];

    protected function casts(): array
    {
        return [
            'default_days_per_year' => 'integer',
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
            'requires_director_approval' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approver_id');
    }

    public function directorApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_approver_id');
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
