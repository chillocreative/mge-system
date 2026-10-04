<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReferenceSetting extends Model
{
    protected $fillable = [
        'project_id', 'company_code', 'client_code', 'primary_project_code',
        'alternate_project_code', 'volume_code',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
