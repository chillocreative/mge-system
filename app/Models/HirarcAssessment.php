<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HirarcAssessment extends Model
{
    protected $fillable = [
        'project_id', 'title', 'process', 'location',
        'assessment_date', 'review_date', 'status', 'prepared_by',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date:Y-m-d',
            'review_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HirarcItem::class)->orderBy('sort_order');
    }
}
