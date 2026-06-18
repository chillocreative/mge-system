<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'registration_no', 'make', 'model', 'year', 'type',
        'purchase_date', 'current_value', 'assigned_to', 'status',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'current_value' => 'decimal:2',
            'year' => 'integer',
        ];
    }

    // ── Relationships ──

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function maintenanceLogs(): MorphMany
    {
        return $this->morphMany(MaintenanceLog::class, 'maintainable');
    }

    // ── Scopes ──

    public function scopeByStatus($q, string $s)
    {
        return $q->where('status', $s);
    }

    public function scopeByType($q, string $t)
    {
        return $q->where('type', $t);
    }

    public function scopeSearch($q, string $term)
    {
        return $q->where(function ($query) use ($term) {
            $query->where('registration_no', 'like', "%{$term}%")
                ->orWhere('make', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%");
        });
    }

    // Vehicles that have at least one document expiring within $days
    public function scopeWithExpiringDocuments($q, int $days = 30)
    {
        return $q->whereHas('documents', fn ($d) => $d->expiringWithin($days));
    }
}
