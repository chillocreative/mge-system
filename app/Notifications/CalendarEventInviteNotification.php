<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalendarEventInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly \App\Models\CalendarEvent $event,
        public readonly string $projectName,
        public readonly string $inviterName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Invitation: {$this->event->title}",
            'message' => "{$this->inviterName} invited you to \"{$this->event->title}\" on ".$this->event->start_datetime->format('d M Y, g:ia').'.',
            'type' => 'calendar',
            'link' => "/projects/{$this->event->project_id}?tab=calendar",
            'extra' => ['project_id' => $this->event->project_id, 'event_id' => $this->event->id],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Invitation: {$this->event->title}")
            ->greeting('Hello '.trim("{$notifiable->first_name} {$notifiable->last_name}").',');

        $mail->line("You have been invited to an event on the \"{$this->projectName}\" project.");
        $mail->line("Event: {$this->event->title}");

        $mail->line($this->formattedTime());

        if ($this->event->location) {
            $mail->line("Location: {$this->event->location}");
        }

        if ($this->event->description) {
            $mail->line("Details: {$this->event->description}");
        }

        $mail->line("Invited by {$this->inviterName}.")
            ->action('Open in MGE-PMS', url("/projects/{$this->event->project_id}?tab=calendar"));

        return $mail;
    }

    protected function formattedTime(): string
    {
        $start = $this->event->start_datetime;

        if ($this->event->all_day) {
            return "All day, {$start->format('d M Y')}";
        }

        if ($this->event->end_datetime) {
            $end = $this->event->end_datetime;

            if ($start->isSameDay($end)) {
                return "{$start->format('d M Y, g:ia')} - {$end->format('g:ia')}";
            }

            return "{$start->format('d M Y, g:ia')} - {$end->format('d M Y, g:ia')}";
        }

        return $start->format('d M Y, g:ia');
    }
}
