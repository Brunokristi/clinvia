<?php

namespace App\Notifications;

use App\Models\AppointmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AppointmentRequest $appointmentRequest,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->appointmentRequest->loadMissing(['branch', 'services']);

        return (new MailMessage)
            ->subject('Žiadosť o rezerváciu bola prijatá')
            ->view('emails.bookings.requested', [
                'patientName' => $this->appointmentRequest->patient_name,
                'serviceName' => $this->serviceName(),
                'branchName' => $this->appointmentRequest->branch?->name ?? '—',
                'requestType' => $this->appointmentRequest->request_type ?? 'preferred_period',
                'preferredDate' => $this->preferredDateLabel(),
                'preferredPeriod' => $this->preferredPeriodLabel($this->appointmentRequest->preferred_period),
                'preferredLabel' => $this->preferredLabel(),
                'patientNote' => $this->appointmentRequest->patient_note,
            ]);
    }

    private function serviceName(): string
    {
        if ($this->appointmentRequest->services->isNotEmpty()) {
            return $this->appointmentRequest->services
                ->pluck('name')
                ->join(', ');
        }

        return '—';
    }

    private function preferredDateLabel(): ?string
    {
        if (! $this->appointmentRequest->preferred_date) {
            return null;
        }

        return $this->appointmentRequest->preferred_date->format('d.m.Y');
    }

    private function preferredLabel(): string
    {
        if (($this->appointmentRequest->request_type ?? 'preferred_period') === 'general') {
            return 'Pacient nevybral konkrétny deň ani čas. Termín navrhne sestra.';
        }

        $date = $this->preferredDateLabel();
        $period = $this->preferredPeriodLabel($this->appointmentRequest->preferred_period);

        if ($date && $period) {
            return "{$date}, {$period}";
        }

        if ($date) {
            return $date;
        }

        if ($period) {
            return $period;
        }

        return '—';
    }

    private function preferredPeriodLabel(?string $period): ?string
    {
        return match ($period) {
            'morning' => 'Ráno',
            'forenoon' => 'Dopoludnia',
            'afternoon' => 'Popoludní',
            'evening' => 'Večer',
            default => $period,
        };
    }
}