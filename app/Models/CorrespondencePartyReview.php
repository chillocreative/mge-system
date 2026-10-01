<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondencePartyReview extends Model
{
    public const ROLES = ['jpriz', 'jps', 'client', 'subcontractor'];

    public const NORMALIZED_STATUSES = [
        'open', 'pending', 'received', 'approved', 'accepted', 'replied',
        'resolved', 'declined', 'rejected', 'resubmit', 'closed', 'other',
    ];

    protected $fillable = [
        'project_correspondence_id', 'party_role', 'project_party_id', 'status_raw',
        'status_normalized', 'decision_date', 'closed_date', 'remarks', 'sequence',
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'date:Y-m-d',
            'closed_date' => 'date:Y-m-d',
            'sequence' => 'integer',
        ];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(ProjectCorrespondence::class, 'project_correspondence_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(ProjectParty::class, 'project_party_id');
    }
}
