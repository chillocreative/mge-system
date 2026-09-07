<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a correspondence's append-only history (Batch 7): raised, handed
 * to a party, noted, status-changed, closed. Never edited or deleted — it is
 * the audit trail of how the correspondence moved.
 */
class CorrespondenceEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_correspondence_id', 'event_type', 'note',
        'from_party_id', 'to_party_id', 'from_status', 'to_status', 'created_by',
    ];

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(ProjectCorrespondence::class, 'project_correspondence_id');
    }

    public function fromParty(): BelongsTo
    {
        return $this->belongsTo(ProjectParty::class, 'from_party_id');
    }

    public function toParty(): BelongsTo
    {
        return $this->belongsTo(ProjectParty::class, 'to_party_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
