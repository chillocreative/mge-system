<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteFile extends Model
{
    protected $fillable = [
        'meeting_minute_id', 'file_path', 'file_name', 'file_type', 'file_size',
    ];

    public function meeting(): BelongsTo { return $this->belongsTo(MeetingMinute::class, 'meeting_minute_id'); }
}
