<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectCorrespondence extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'site_id', 'type', 'reference_no', 'title', 'description',
        'status', 'raised_date', 'due_date', 'response', 'created_by',
        'current_party_id', 'expected_close_date', 'actual_close_date', 'closing_reference', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'raised_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'expected_close_date' => 'date:Y-m-d',
            'actual_close_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectCorrespondenceFile::class);
    }

    public function currentParty(): BelongsTo
    {
        return $this->belongsTo(ProjectParty::class, 'current_party_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CorrespondenceEvent::class)->orderBy('created_at');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function scopeByStatus($q, string $status)
    {
        return $q->where('status', $status);
    }

    public function scopeForProject($q, int $id)
    {
        return $q->where('project_id', $id);
    }
}
