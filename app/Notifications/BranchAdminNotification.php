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
        protected string $message,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->subject)
            ->line($this->message)
            ->line('Ak chcete vyriešiť túto udalosť, otvorte svoju administráciu.');
    }
}
