<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BranchAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $subject,
        protected string $bodyText,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view('emails.notifications.notification', [
                'subject' => $this->subject,
                'bodyText' => $this->bodyText,
            ]);
    }
}