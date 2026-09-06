<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'year', 'entitled_days', 'used_days', 'remaining_days',
        'carried_forward', 'adjustment_days', 'rule_snapshot', 'calculated_at', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled_days' => 'decimal:1',
            'used_days' => 'decimal:1',
            'remaining_days' => 'decimal:1',
            'carried_forward' => 'decimal:2',
            'adjustment_days' => 'decimal:2',
            'rule_snapshot' => 'array',
            'calculated_at' => 'datetime',
            'is_locked' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
