<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReferenceSequence extends Model
{
    protected $fillable = ['project_reference_template_id', 'period_key', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectReferenceTemplate::class, 'project_reference_template_id');
    }
}
