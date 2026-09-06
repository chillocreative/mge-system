<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicySetting extends Model
{
    protected $fillable = [
        'leave_type_id', 'key', 'value', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }

    // ── Relationships ──

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    // ── Scopes ──

    public function scopeGlobal($query)
    {
        return $query->whereNull('leave_type_id');
    }
}
