<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItem extends Model
{
    protected $fillable = [
        'meeting_minute_id', 'item', 'assigned_to', 'due_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MeetingMinute::class, 'meeting_minute_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
