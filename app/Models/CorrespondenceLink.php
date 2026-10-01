<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceLink extends Model
{
    public const RELATION_TYPES = ['response_to', 'resubmission_of', 'supersedes', 'closes', 'related'];

    protected $fillable = [
        'source_correspondence_id', 'target_correspondence_id', 'relation_type', 'note', 'created_by',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProjectCorrespondence::class, 'source_correspondence_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(ProjectCorrespondence::class, 'target_correspondence_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
