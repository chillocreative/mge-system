<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SafetyIncident extends Model
{
    protected $fillable = [
        'project_id', 'reported_by', 'title', 'description',
        'incident_date', 'incident_time', 'location',
        'severity', 'type', 'injured_person', 'injury_description',
        'root_cause', 'corrective_action', 'preventive_action',
        'status', 'investigated_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function investigator(): BelongsTo { return $this->belongsTo(User::class, 'investigated_by'); }
    public function photos(): MorphMany { return $this->morphMany(ReportPhoto::class, 'photoable'); }

    public function scopeByStatus($q, string $s) { return $q->where('status', $s); }
    public function scopeBySeverity($q, string $s) { return $q->where('severity', $s); }
    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }
}
