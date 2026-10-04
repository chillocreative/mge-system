<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrespondenceRfaSubtype extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
