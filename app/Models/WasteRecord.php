<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WasteRecord extends Model
{
    protected $fillable = [
        'project_id', 'recorded_by', 'waste_type', 'description',
        'quantity', 'unit', 'disposal_method', 'disposal_date',
        'hauler', 'manifest_number', 'destination', 'status',
    ];

    protected function casts(): array
    {
        return ['disposal_date' => 'date', 'quantity' => 'decimal:2'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function photos(): MorphMany { return $this->morphMany(ReportPhoto::class, 'photoable'); }

    public function scopeByStatus($q, string $s) { return $q->where('status', $s); }
    public function scopeByType($q, string $t) { return $q->where('waste_type', $t); }
    public function scopeForProject($q, int $id) { return $q->where('project_id', $id); }
}
