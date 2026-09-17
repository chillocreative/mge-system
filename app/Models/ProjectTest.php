<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTest extends Model
{
    protected $fillable = [
        'project_id',
        'ref_no',
        'name',
        'test_date',
        'result',
        'remarks',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'test_date' => 'date:Y-m-d',
    ];
}
