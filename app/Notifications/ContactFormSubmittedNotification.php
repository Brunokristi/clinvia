<?php

namespace App\Notifications;

use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactFormSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Branch $branch,
        protected string $senderName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Vaša správa bola odoslaná')
            ->view('emails.notifications.submitted', [
                'branch' => $this->branch,
                'senderName' => $this->senderName,
            ]);
    }
}