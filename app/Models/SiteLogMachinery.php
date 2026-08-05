<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteLogMachinery extends Model
{
    protected $table = 'site_log_machinery';

    protected $fillable = [
        'site_log_id',
        'machinery_type',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function siteLog(): BelongsTo
    {
        return $this->belongsTo(SiteLog::class);
    }
}
