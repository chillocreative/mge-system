<?php

namespace App\Events;

use App\Models\InternalEmail;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewInternalEmail implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public InternalEmail $email,
        public int $recipientUserId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recipientUserId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->email->id,
            'subject' => $this->email->subject,
            'sender' => [
                'id' => $this->email->sender->id,
                'first_name' => $this->email->sender->first_name,
                'last_name' => $this->email->sender->last_name,
            ],
            'sent_at' => $this->email->sent_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'email.received';
    }
}
