<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EnvironmentalAudit extends Model
{
    protected $fillable = [
        'project_id', 'auditor_id', 'title', 'audit_date', 'type',
        'scope', 'findings', 'non_conformities', 'corrective_actions',
        'status', 'next_audit_date',
    ];

    protected function casts(): array
    {
        return ['audit_date' => 'date', 'next_audit_date' => 'date'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function auditor(): BelongsTo { return $this->belongsTo(User::class, 'auditor_id'); }
    public function photos(): MorphMany { return $this->morphMany(ReportPhoto::class, 'photoable'); }

    public function scopeByStatus($q, string $s) { return $q->where('status', $s); }
    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }
}
