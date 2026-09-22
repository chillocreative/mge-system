<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnvironmentReport extends Model
{
    use SoftDeletes;

    public const KINDS = ['policy_override', 'location_override', 'bmp_photo', 'consultant_cert'];

    public const SECTION_KEYS = [
        'contract',
        'ems',
        'introduction',
        'flow_chart',
        'policy',
        'location',
        'parameters',
        'results',
        'bmp',
    ];

    protected $fillable = [
        'project_id',
        'report_no',
        'title',
        'period_start',
        'period_end',
        'status',
        'signatories',
        'sections',
        'generated_at',
        'finalised_at',
        'created_by',
        'updated_by',
        'finalised_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'signatories' => 'array',
            'sections' => 'array',
            'generated_at' => 'datetime',
            'finalised_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(EnvironmentReportAsset::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finaliser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalised_by');
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
