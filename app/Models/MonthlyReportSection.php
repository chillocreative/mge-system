<?php

namespace App\Models;

use App\Services\MonthlyReport\SectionMerger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportSection extends Model
{
    protected $fillable = ['report_id', 'key', 'title', 'sort_order', 'include', 'data', 'overrides', 'overrides_at', 'notes', 'regenerated_at'];

    protected $appends = ['merged'];

    protected function casts(): array
    {
        return ['include' => 'boolean', 'data' => 'array', 'overrides' => 'array', 'overrides_at' => 'datetime', 'regenerated_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'report_id');
    }

    public function getMergedAttribute(): array
    {
        return SectionMerger::merge($this->data ?? ['schema' => 1], $this->overrides);
    }
}
