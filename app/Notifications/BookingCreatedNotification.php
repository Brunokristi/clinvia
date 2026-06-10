<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing([
            'branch',
            'service',
            'services',
            'bookingSlot',
            'capacityWindow',
        ]);

        $startsAt = $this->booking->starts_at
            ?? $this->booking->capacityWindow?->starts_at
            ?? $this->booking->bookingSlot?->starts_at;

        $endsAt = $this->booking->ends_at
            ?? $this->booking->capacityWindow?->ends_at
            ?? $this->booking->bookingSlot?->ends_at;

        $appointmentLabel = '—';

        if ($startsAt) {
            $appointmentLabel = $startsAt->format('d.m.Y H:i');

            if ($endsAt) {
                $appointmentLabel .= ' – ' . $endsAt->format('H:i');
            }
        }

        return (new MailMessage)
            ->subject('Nová rezervácia')
            ->view('emails.bookings.created', [
                'patientName' => $this->booking->patient_name,
                'serviceName' => $this->booking->services->isNotEmpty()
                    ? $this->booking->services->pluck('name')->join(', ')
                    : ($this->booking->service?->name ?? '—'),
                'branchName' => $this->booking->branch?->name ?? '—',
                'appointmentLabel' => $appointmentLabel,
                'patientNote' => $this->booking->patient_note,
            ]);
    }
}