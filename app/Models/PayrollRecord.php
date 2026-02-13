<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'total_working_days',
        'total_present_days',
        'total_absent_days',
        'total_late_days',
        'total_working_hours',
        'total_overtime_hours',
        'base_salary',
        'hourly_rate',
        'overtime_pay',
        'deductions',
        'net_salary',
        'status',
        'generated_by',
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_working_hours' => 'decimal:2',
            'total_overtime_hours' => 'decimal:2',
            'base_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ──

    public function scopeForPeriod($query, string $start, string $end)
    {
        return $query->where('period_start', $start)->where('period_end', $end);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
