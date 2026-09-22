<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentProjectSetting extends Model
{
    protected $fillable = [
        'project_id',
        'consultant_company',
        'consultant_name',
        'consultant_reg_no',
        'officer_name',
        'officer_reg_no',
        'policy_image_path',
        'location_map_path',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
