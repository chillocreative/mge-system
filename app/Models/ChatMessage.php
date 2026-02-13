<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_room_id',
        'user_id',
        'body',
        'type',
        'file_path',
        'file_name',
        'file_size',
    ];

    // ── Relationships ──

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Helpers ──

    public function isFile(): bool
    {
        return in_array($this->type, ['file', 'image']);
    }
}
