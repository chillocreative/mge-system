<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected $fillable = [
        'name', 'date', 'year', 'scope', 'state', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
