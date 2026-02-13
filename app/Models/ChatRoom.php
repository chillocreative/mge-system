<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatRoom extends Model
{
    protected $fillable = [
        'name',
        'type',
        'project_id',
        'created_by',
    ];

    // ── Relationships ──

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    // ── Scopes ──

    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('members', fn ($q) => $q->where('user_id', $userId));
    }

    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Helpers ──

    public function getDisplayNameForUser(User $user): string
    {
        if ($this->type !== 'private' || $this->name) {
            return $this->name ?? 'Unnamed Room';
        }

        // For private chats, show the other user's name
        $other = $this->members->firstWhere('id', '!=', $user->id);
        return $other ? ($other->first_name . ' ' . $other->last_name) : 'Private Chat';
    }

    public function unreadCountForUser(int $userId): int
    {
        $pivot = $this->members()->where('user_id', $userId)->first()?->pivot;
        $lastRead = $pivot?->last_read_at;

        $query = $this->messages()->where('user_id', '!=', $userId);
        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->count();
    }
}
