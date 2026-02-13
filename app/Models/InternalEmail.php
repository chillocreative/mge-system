<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalEmail extends Model
{
    protected $fillable = [
        'thread_id',
        'parent_id',
        'from_user_id',
        'subject',
        'body',
        'is_draft',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(InternalEmail::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(InternalEmail::class, 'thread_id', 'id')
            ->where('id', '!=', $this->id)
            ->orderBy('created_at');
    }

    public function threadEmails(): HasMany
    {
        return $this->hasMany(InternalEmail::class, 'thread_id', 'thread_id')
            ->orderBy('created_at');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailRecipient::class, 'email_id');
    }

    public function toRecipients(): HasMany
    {
        return $this->recipients()->where('type', 'to');
    }

    public function ccRecipients(): HasMany
    {
        return $this->recipients()->where('type', 'cc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class, 'email_id');
    }

    // ── Scopes ──

    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at')->where('is_draft', false);
    }

    public function scopeDrafts($query)
    {
        return $query->where('is_draft', true);
    }

    public function scopeThreadStarters($query)
    {
        return $query->whereColumn('id', 'thread_id');
    }
}
