<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class HazardReport extends Model
{
    protected $fillable = [
        'project_id', 'reported_by', 'title', 'description', 'location',
        'hazard_type', 'risk_level', 'recommended_action', 'corrective_action',
        'status', 'assigned_to', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function photos(): MorphMany { return $this->morphMany(ReportPhoto::class, 'photoable'); }

    public function scopeByStatus($q, string $s) { return $q->where('status', $s); }
    public function scopeByRisk($q, string $r) { return $q->where('risk_level', $r); }
    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }
}
