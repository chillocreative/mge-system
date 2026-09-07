<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic notification. Stored in-app on the database channel, and optionally
 * emailed — but which channels actually run is decided by NotificationService
 * from the recipient's preferences and the global email switch, then passed in
 * here. This class does not decide policy; it only renders each channel.
 *
 * Mail stays off unless config('notifications.email_enabled') is true, so a
 * misconfigured SMTP can never break a workflow (go-live decision AB9).
 */
class SystemNotification extends Notification
{
    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        private string $title,
        private string $message,
        private string $type = 'info',
        private ?string $link = null,
        private array $extra = [],
        private array $channels = ['database'],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
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

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting($this->title)
            ->line($this->message);

        if ($this->link) {
            $url = str_starts_with($this->link, 'http')
                ? $this->link
                : rtrim(config('app.url'), '/').'/'.ltrim($this->link, '/');
            $mail->action('View in MGE-PMS', $url);
        }

        return $mail;
    }
}
