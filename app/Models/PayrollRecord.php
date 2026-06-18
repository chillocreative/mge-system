<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'period_start',
        'period_end',
        'total_working_days',
        'total_present_days',
        'total_absent_days',
        'total_late_days',
        'total_working_hours',
        'total_overtime_hours',
        'base_salary',
        'gross_salary',
        'allowances',
        'bonus',
        'hourly_rate',
        'overtime_pay',
        'epf_employee',
        'epf_employer',
        'socso_employee',
        'socso_employer',
        'eis_employee',
        'eis_employer',
        'pcb',
        'zakat',
        'deductions',
        'net_salary',
        'status',
        'generated_by',
        'approved_by',
        'notes',
        'payslip_path',
        'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_working_hours' => 'decimal:2',
            'total_overtime_hours' => 'decimal:2',
            'base_salary' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'bonus' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'epf_employee' => 'decimal:2',
            'epf_employer' => 'decimal:2',
            'socso_employee' => 'decimal:2',
            'socso_employer' => 'decimal:2',
            'eis_employee' => 'decimal:2',
            'eis_employer' => 'decimal:2',
            'pcb' => 'decimal:2',
            'zakat' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'email_sent_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductionItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PayrollDeduction::class);
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
