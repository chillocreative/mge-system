<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCorrespondenceFile extends Model
{
    protected $fillable = [
        'project_correspondence_id', 'file_path', 'file_name', 'file_type', 'file_size',
    ];

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(ProjectCorrespondence::class, 'project_correspondence_id');
    }
}
