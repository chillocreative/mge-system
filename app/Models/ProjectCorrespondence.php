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
        'project_id', 'type', 'reference_no', 'title', 'description',
        'status', 'raised_date', 'due_date', 'response', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'raised_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function files(): HasMany { return $this->hasMany(ProjectCorrespondenceFile::class); }

    public function scopeByType($q, string $type) { return $q->where('type', $type); }
    public function scopeByStatus($q, string $status) { return $q->where('status', $status); }
    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }
}
