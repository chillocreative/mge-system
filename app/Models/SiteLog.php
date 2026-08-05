<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteLog extends Model
{
    protected $fillable = [
        'project_id',
        'log_date',
        'title',
        'description',
        'weather',
        'workers_count',
        'work_performed',
        'materials_used',
        'equipment_used',
        'safety_notes',
        'issues',
        'logged_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date:Y-m-d',
            'workers_count' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function machinery(): HasMany
    {
        return $this->hasMany(SiteLogMachinery::class);
    }

    public function weatherEvents(): HasMany
    {
        return $this->hasMany(SiteLogWeatherEvent::class)->orderBy('event_time');
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('log_date', [$from, $to]);
    }
}
