<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentReportAsset extends Model
{
    protected $fillable = [
        'environment_report_id',
        'kind',
        'caption',
        'sort_order',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(EnvironmentReport::class, 'environment_report_id');
    }
}
