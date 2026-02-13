<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailRecipient extends Model
{
    protected $fillable = [
        'email_id',
        'user_id',
        'type',
        'read_at',
        'starred',
        'trashed_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'trashed_at' => 'datetime',
            'starred' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function email(): BelongsTo
    {
        return $this->belongsTo(InternalEmail::class, 'email_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ──

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeStarred($query)
    {
        return $query->where('starred', true);
    }

    public function scopeNotTrashed($query)
    {
        return $query->whereNull('trashed_at');
    }

    public function scopeTrashed($query)
    {
        return $query->whereNotNull('trashed_at');
    }
}
