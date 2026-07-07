<?php

namespace App\Notifications;

use App\Models\AppointmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AppointmentRequest $appointmentRequest,
        private readonly ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->appointmentRequest->loadMissing(['branch', 'services']);

        $mail = (new MailMessage())
            ->subject('Žiadosť o termín bola zamietnutá')
            ->line('Dobrý deň ' . ($this->appointmentRequest->patient_name ?: '') . ',')
            ->line('Vašu žiadosť o termín sa nepodarilo potvrdiť.');

        if ($this->reason) {
            $mail->line('Dôvod: ' . $this->reason);
        }

        return $mail
            ->line('Pobočka: ' . ($this->appointmentRequest->branch?->name ?? '—'))
            ->line('Ak máte záujem, vytvorte prosím novú žiadosť.');
    }
}
