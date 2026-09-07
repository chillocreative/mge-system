<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Permit To Work (Ciri 25). See the migration for the state machine.
 *
 * `effective_status` is the value the rest of the app should trust: it folds
 * expiry into the stored status so an approved-but-past-window permit reads as
 * "expired" everywhere without a background job having to touch the row.
 */
class WorkPermit extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'requested_by', 'permit_no', 'title', 'type', 'location',
        'description', 'precautions', 'valid_from', 'valid_to', 'status',
        'approved_by', 'approved_at', 'decision_notes', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $appends = ['effective_status'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * The status callers should act on. Only an approved permit can "expire":
     * a draft/pending/rejected/closed permit keeps its stored status regardless
     * of the clock.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'approved' && $this->valid_to && $this->valid_to->isPast()) {
            return 'expired';
        }

        return $this->status;
    }
}
