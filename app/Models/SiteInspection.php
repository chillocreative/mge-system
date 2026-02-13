<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SiteInspection extends Model
{
    protected $fillable = [
        'project_id', 'inspector_id', 'title', 'inspection_date', 'type',
        'findings', 'recommendations', 'overall_status',
        'follow_up_required', 'follow_up_date', 'corrective_actions',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'follow_up_date' => 'date',
            'follow_up_required' => 'boolean',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspector_id'); }
    public function photos(): MorphMany { return $this->morphMany(ReportPhoto::class, 'photoable'); }

    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }
}
