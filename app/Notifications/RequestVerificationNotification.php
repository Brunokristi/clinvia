<?php

namespace App\Notifications;

use App\Models\AppointmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AppointmentRequest $appointmentRequest,
        private readonly string $verificationUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->appointmentRequest->loadMissing(['branch', 'services']);

        return (new MailMessage())
            ->subject('Potvrďte požiadavku na rezerváciu')
            ->greeting('Dobrý deň ' . ($this->appointmentRequest->patient_name ?: '') . ',')
            ->line('Prišla nám požiadavka na rezerváciu.')
            ->line('Služba: ' . $this->serviceLabel())
            ->line('Preferovaný termín: ' . ($this->preferredLabel() ?? 'Neuvedené'))
            ->action('Potvrdiť požiadavku', $this->verificationUrl)
            ->line('Ak ste túto požiadavku nevytvorili, tento email môžete ignorovať.');
    }

    private function serviceLabel(): string
    {
        return $this->appointmentRequest->services
            ->pluck('name')
            ->filter()
            ->join(', ') ?: 'Neuvedené';
    }

    private function preferredLabel(): ?string
    {
        if ($this->appointmentRequest->preferred_starts_at) {
            return $this->appointmentRequest->preferred_starts_at->format('d.m.Y H:i');
        }

        if ($this->appointmentRequest->preferred_date && $this->appointmentRequest->preferred_period) {
            return $this->appointmentRequest->preferred_date->format('d.m.Y') . ', ' . $this->appointmentRequest->preferred_period;
        }

        if ($this->appointmentRequest->preferred_date) {
            return $this->appointmentRequest->preferred_date->format('d.m.Y');
        }

        return $this->appointmentRequest->preferred_time_note;
    }
}
