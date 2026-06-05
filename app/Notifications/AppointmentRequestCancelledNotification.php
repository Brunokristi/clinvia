<?php

namespace App\Notifications;

use App\Models\AppointmentRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentRequestCancelledNotification extends Notification
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

        $services = $this->appointmentRequest->services
            ->pluck('name')
            ->filter()
            ->join(', ');

        $preferredDate = $this->formatDate($this->appointmentRequest->preferred_date);
        $preferredPeriod = $this->formatPeriod($this->appointmentRequest->preferred_period);

        $preferredLabel = collect([
            $preferredDate,
            $preferredPeriod,
        ])
            ->filter()
            ->join(' · ');

        return (new MailMessage)
            ->subject('Žiadosť o termín bola zrušená')
            ->view('emails.appointment.cancelled', [
                'patientName' => $this->appointmentRequest->patient_name,
                'serviceName' => $services ?: '—',
                'branchName' => $this->appointmentRequest->branch?->name ?? '—',
                'preferredLabel' => $preferredLabel ?: null,
                'reason' => $this->reason,
            ]);
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof CarbonInterface
            ? $value->format('d.m.Y')
            : Carbon::parse($value)->format('d.m.Y');
    }

    private function formatPeriod(?string $period): ?string
    {
        if (! $period) {
            return null;
        }

        return [
            'morning' => 'Ráno',
            'forenoon' => 'Dopoludnia',
            'afternoon' => 'Popoludní',
            'evening' => 'Večer',
            'rano' => 'Ráno',
            'dopoludnia' => 'Dopoludnia',
            'popoludni' => 'Popoludní',
            'vecer' => 'Večer',
        ][$period] ?? $period;
    }
}