<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'type',
        'start_datetime',
        'end_datetime',
        'all_day',
        'location',
        'attendees',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'all_day' => 'boolean',
            'attendees' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>=', now())
            ->where('status', 'scheduled')
            ->orderBy('start_datetime');
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->whereBetween('start_datetime', [$from, $to])
                ->orWhereBetween('end_datetime', [$from, $to]);
        });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
