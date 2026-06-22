<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoRecipient extends Model
{
    protected $fillable = ['memo_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
