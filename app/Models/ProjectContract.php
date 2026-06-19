<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectContract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'title',
        'contract_no',
        'contract_value',
        'start_date',
        'end_date',
        'pic_name',
        'pic_email',
        'pic_phone',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    // Relationships

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectContractFile::class);
    }

    // Scopes

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
