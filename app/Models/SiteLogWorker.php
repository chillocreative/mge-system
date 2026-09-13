<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteLogWorker extends Model
{
    protected $table = 'site_log_workers';

    protected $fillable = [
        'site_log_id',
        'worker_type',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
        ];
    }

    public function siteLog(): BelongsTo
    {
        return $this->belongsTo(SiteLog::class);
    }
}
