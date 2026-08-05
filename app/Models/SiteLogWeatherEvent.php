<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteLogWeatherEvent extends Model
{
    protected $fillable = [
        'site_log_id',
        'condition',
        'event_time',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime:H:i',
        ];
    }

    public function siteLog(): BelongsTo
    {
        return $this->belongsTo(SiteLog::class);
    }
}
