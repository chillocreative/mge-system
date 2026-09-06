<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveDay extends Model
{
    protected $fillable = [
        'leave_request_id', 'employee_id', 'leave_type_id', 'date', 'year',
        'fraction', 'is_deducted', 'exclusion_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'year' => 'integer',
            'fraction' => 'decimal:2',
            'is_deducted' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    // ── Scopes ──

    public function scopeDeducted($query)
    {
        return $query->where('is_deducted', true);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
