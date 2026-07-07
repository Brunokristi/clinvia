<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingChangeSummaryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $subject,
        private readonly string $headline,
        private readonly array $diffLines,
        private readonly ?string $scopeMessage = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject($this->subject)
            ->line($this->headline);

        if ($this->scopeMessage) {
            $mail->line($this->scopeMessage);
        }

        foreach ($this->diffLines as $line) {
            $mail->line('• ' . $line);
        }

        return $mail;
    }
}
