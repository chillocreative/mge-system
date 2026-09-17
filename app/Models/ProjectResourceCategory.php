<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectResourceCategory extends Model
{
    protected $fillable = [
        'project_id',
        'kind',
        'group',
        'name',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
