<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'start_datetime',
        'end_datetime',
        'all_day',
        'recurrence',
        'recurrence_until',
        'location',
        'employee_id',
        'project_id',
        'source',
        'google_event_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_until' => 'date:Y-m-d',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendees(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'company_event_attendees')->withTimestamps();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForRange(Builder $query, string $start, string $end): Builder
    {
        return $query->where(function ($q) use ($start, $end) {
            // Event starts within range, OR spans across the start of the range.
            $q->whereBetween('start_datetime', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->whereNotNull('end_datetime')
                        ->where('start_datetime', '<=', $start)
                        ->where('end_datetime', '>=', $start)
                        ->where('start_datetime', '<=', $end);
                });
        });
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
