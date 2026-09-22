<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $title,
        private string $message,
        private string $actionType,
        private ?int $leaveRequestId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_type' => $this->actionType,
            'leave_request_id' => $this->leaveRequestId,
            'type' => 'leave',
            'link' => $this->approverAction()
                ? '/hr/leave/approvals'
                : '/leave/my',
        ];
    }

    /**
     * True when this notification is asking an approver (manager or director) to act,
     * as opposed to informing the employee of a decision on their own request.
     */
    private function approverAction(): bool
    {
        return $this->actionType === 'leave_submitted'
            || str_starts_with($this->actionType, 'leave_awaiting_');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.trim("{$notifiable->first_name} {$notifiable->last_name}").',')
            ->line($this->message)
            ->action('Open MGE-PMS', url('/hr/leave'));
    }
}
