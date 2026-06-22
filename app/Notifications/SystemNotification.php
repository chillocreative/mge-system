<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Generic in-app notification stored on the database channel.
 * Mail is intentionally excluded so SMTP misconfiguration never breaks workflows.
 */
class SystemNotification extends Notification
{
    public function __construct(
        private string $title,
        private string $message,
        private string $type = 'info',
        private ?string $link = null,
        private array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'link' => $this->link,
            'extra' => $this->extra,
        ];
    }
}
