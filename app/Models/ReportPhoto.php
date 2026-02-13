<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReportPhoto extends Model
{
    protected $fillable = ['file_path', 'file_name', 'caption', 'file_size'];

    public function photoable(): MorphTo
    {
        return $this->morphTo();
    }
}
