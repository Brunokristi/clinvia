<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GroupEventReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $participantName,
        private readonly string $eventTitle,
        private readonly string $branchName,
        private readonly Carbon $startsAt,
        private readonly ?Carbon $endsAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $timeLabel = $this->startsAt->format('d.m.Y H:i');

        if ($this->endsAt) {
            $timeLabel .= ' - ' . $this->endsAt->format('H:i');
        }

        return (new MailMessage())
            ->subject('Pripomienka skupinového termínu na zajtra')
            ->line('Dobrý deň ' . ($this->participantName ?: '') . ',')
            ->line('Pripomíname vám zajtrajší skupinový termín.')
            ->line('Termín: ' . $timeLabel)
            ->line('Udalosť: ' . $this->eventTitle)
            ->line('Pobočka: ' . $this->branchName)
            ->line('Prosíme, príďte na čas.');
    }
}
