<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BranchContactReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $subject,
        protected string $bodyText,
        protected ?string $branchName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->subject)
            ->view('emails.notifications.reply', [
                'subject' => $this->subject,
                'bodyText' => $this->bodyText,
                'branchName' => $this->branchName,
            ]);
    }
}