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
            ->subject('Potvrďte požiadavku na termín')
            ->view('emails.appointments.request_verification', [
                'contactName' => $this->contactName(),
                'serviceLabel' => $this->serviceLabel(),
                'preferredLabel' => $this->preferredLabel() ?? 'Neuvedené',
                'patientName' => $this->appointmentRequest->patient_name ?: 'Neuvedené',
                'contactLabel' => $this->contactLabel(),
                'verificationUrl' => $this->verificationUrl,
            ]);
    }

    private function contactName(): string
    {
        return (string) ($this->appointmentRequest->requester_name
            ?: $this->appointmentRequest->patient_name
            ?: '');
    }

    private function contactLabel(): string
    {
        $contact = [
            $this->appointmentRequest->requester_name,
            $this->appointmentRequest->requester_email,
            $this->appointmentRequest->requester_phone,
        ];

        $normalized = collect($contact)
            ->filter(fn ($value) => filled($value))
            ->values()
            ->all();

        if ($normalized !== []) {
            return implode(' · ', $normalized);
        }

        $fallback = [
            $this->appointmentRequest->patient_name,
            $this->appointmentRequest->patient_email,
            $this->appointmentRequest->patient_phone,
        ];

        return collect($fallback)
            ->filter(fn ($value) => filled($value))
            ->values()
            ->implode(' · ') ?: 'Neuvedené';
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
