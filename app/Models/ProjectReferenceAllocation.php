<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReferenceAllocation extends Model
{
    protected $fillable = [
        'project_id', 'project_reference_template_id', 'period_key',
        'sequence_number', 'reference_no', 'generated_by',
    ];

    protected function casts(): array
    {
        return ['sequence_number' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectReferenceTemplate::class, 'project_reference_template_id');
    }
}
