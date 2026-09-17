<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINAL = 'final';

    protected $fillable = ['project_id', 'period_id', 'report_no', 'title', 'month_label', 'evaluation_date', 'status', 'signatories', 'options', 'generated_at', 'generated_by', 'finalised_at', 'finalised_by'];

    protected function casts(): array
    {
        return ['evaluation_date' => 'date:Y-m-d', 'signatories' => 'array', 'options' => 'array', 'generated_at' => 'datetime', 'finalised_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ProjectProgressPeriod::class, 'period_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MonthlyReportSection::class, 'report_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(MonthlyReportAsset::class, 'report_id')->orderBy('sort_order');
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }
}
