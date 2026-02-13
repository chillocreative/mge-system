<?php

use App\Models\ChatRoom;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Register authorization callbacks for private and presence channels.
|
*/

// Chat room channel — only members can listen
Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    return ChatRoom::where('id', $roomId)
        ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});

// User's private notification channel
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
