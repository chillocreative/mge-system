<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectScheduleBaseline extends Model
{
    protected $fillable = [
        'project_id',
        'month',
        'scheduled_physical_pct',
        'scheduled_financial_amount',
        'scheduled_financial_pct',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
            'scheduled_physical_pct' => 'decimal:2',
            'scheduled_financial_amount' => 'decimal:2',
            'scheduled_financial_pct' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
