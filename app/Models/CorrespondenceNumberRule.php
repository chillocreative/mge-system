<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceNumberRule extends Model
{
    protected $fillable = [
        'project_id', 'correspondence_type', 'document_subtype', 'pattern', 'padding',
        'next_number', 'reset_annually', 'sequence_year',
    ];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'next_number' => 'integer',
            'reset_annually' => 'boolean',
            'sequence_year' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
