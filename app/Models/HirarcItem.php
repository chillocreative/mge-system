<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HirarcItem extends Model
{
    protected $fillable = [
        'hirarc_assessment_id', 'hazard', 'risk', 'existing_control',
        'likelihood', 'severity', 'risk_rating', 'risk_level',
        'recommended_control', 'pic', 'due_date', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'likelihood' => 'integer',
            'severity' => 'integer',
            'risk_rating' => 'integer',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(HirarcAssessment::class, 'hirarc_assessment_id');
    }
}
