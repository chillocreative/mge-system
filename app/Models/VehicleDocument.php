<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    protected $fillable = [
        'vehicle_id', 'doc_type', 'provider', 'policy_or_ref_no',
        'amount', 'start_date', 'expiry_date', 'file_path', 'file_name', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expiry_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    // ── Scopes ──

    public function scopeExpiringWithin($q, int $days = 30)
    {
        return $q->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('doc_type', $type);
    }
}
