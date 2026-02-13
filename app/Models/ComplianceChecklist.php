<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ComplianceChecklist extends Model
{
    protected $fillable = [
        'project_id', 'inspector_id', 'title', 'type',
        'checklist_date', 'overall_status', 'notes',
    ];

    protected function casts(): array
    {
        return ['checklist_date' => 'date'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspector_id'); }
    public function items(): HasMany { return $this->hasMany(ComplianceChecklistItem::class, 'checklist_id')->orderBy('sort_order'); }
    public function photos(): MorphMany { return $this->morphMany(ReportPhoto::class, 'photoable'); }

    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }

    public function recalculateStatus(): void
    {
        $items = $this->items;
        if ($items->isEmpty()) return;

        $applicable = $items->where('status', '!=', 'na');
        if ($applicable->isEmpty()) {
            $this->update(['overall_status' => 'compliant']);
            return;
        }

        $failCount = $applicable->where('status', 'fail')->count();
        if ($failCount === 0) {
            $this->update(['overall_status' => 'compliant']);
        } elseif ($failCount === $applicable->count()) {
            $this->update(['overall_status' => 'non_compliant']);
        } else {
            $this->update(['overall_status' => 'partial']);
        }
    }
}
