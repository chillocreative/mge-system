<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProgressPeriod extends Model
{
    protected $fillable = [
        'project_id',
        'period_no',
        'period_start',
        'period_end',
        'planning_days_completion',
        'physical_scheduled_pct',
        'physical_actual_pct',
        'financial_scheduled_pct',
        'financial_actual_pct',
        'financial_actual_amount',
        'ahead_delay_days',
        'physical_status',
        'financial_status',
        'notes',
        'created_by',
    ];

    protected $appends = ['physical_variance', 'financial_variance'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'physical_scheduled_pct' => 'decimal:2',
            'physical_actual_pct' => 'decimal:2',
            'financial_scheduled_pct' => 'decimal:2',
            'financial_actual_pct' => 'decimal:2',
            'financial_actual_amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPhysicalVarianceAttribute(): float
    {
        return round((float) $this->physical_actual_pct - (float) $this->physical_scheduled_pct, 2);
    }

    public function getFinancialVarianceAttribute(): float
    {
        return round((float) $this->financial_actual_pct - (float) $this->financial_scheduled_pct, 2);
    }
}
