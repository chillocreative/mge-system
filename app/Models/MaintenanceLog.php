<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'maintainable_type', 'maintainable_id', 'maintenance_type',
        'performed_date', 'next_due_date', 'description', 'cost',
        'vendor', 'performed_by', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'performed_date' => 'date',
            'next_due_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function maintainable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──

    public function scopeByStatus($q, string $s)
    {
        return $q->where('status', $s);
    }

    public function scopeUpcoming($q, int $days = 30)
    {
        return $q->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }
}
