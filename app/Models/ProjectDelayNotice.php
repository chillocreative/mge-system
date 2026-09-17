<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDelayNotice extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'issue',
        'correspondence_id',
        'reg_number',
        'submitted_date',
        'submitted_via',
        'reply_date',
        'status',
        'impact',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'submitted_date' => 'date:Y-m-d',
        'reply_date' => 'date:Y-m-d',
    ];

    protected $appends = ['duration_days'];

    public function getDurationDaysAttribute(): ?int
    {
        if (! $this->submitted_date) {
            return null;
        }
        $end = $this->reply_date ?: now()->startOfDay();

        return (int) $this->submitted_date->diffInDays($end);
    }
}
