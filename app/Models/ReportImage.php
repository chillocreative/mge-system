<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportImage extends Model
{
    public const SECTIONS = ['location', 'site_access', 'progress_key_plan', 'progress'];

    protected $fillable = [
        'project_id',
        'section',
        'label',
        'period_id',
        'file_path',
        'file_name',
        'caption',
        'sort_order',
        'taken_on',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'taken_on' => 'date:Y-m-d',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ProjectProgressPeriod::class, 'period_id');
    }
}
