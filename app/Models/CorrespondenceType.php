<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrespondenceType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'full_name',
        'color',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
