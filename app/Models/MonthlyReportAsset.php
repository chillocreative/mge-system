<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportAsset extends Model
{
    protected $fillable = ['report_id', 'kind', 'file_path', 'file_name', 'sort_order', 'extension', 'size', 'pages'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'pages' => 'integer', 'sort_order' => 'integer'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'report_id');
    }
}
