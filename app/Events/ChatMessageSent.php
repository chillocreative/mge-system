<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.room.' . $this->message->chat_room_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->message->load('sender:id,first_name,last_name');

        return [
            'id' => $this->message->id,
            'chat_room_id' => $this->message->chat_room_id,
            'user_id' => $this->message->user_id,
            'body' => $this->message->body,
            'type' => $this->message->type,
            'file_path' => $this->message->file_path,
            'file_name' => $this->message->file_name,
            'file_size' => $this->message->file_size,
            'sender' => [
                'id' => $this->message->sender->id,
                'first_name' => $this->message->sender->first_name,
                'last_name' => $this->message->sender->last_name,
            ],
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
