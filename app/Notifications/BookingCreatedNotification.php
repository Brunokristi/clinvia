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
        $this->booking->loadMissing(['branch', 'service', 'services', 'bookingSlot']);

        $slot = $this->booking->bookingSlot;

        $appointmentLabel = '—';

        if ($slot?->starts_at) {
            $appointmentLabel = $slot->starts_at->format('d.m.Y H:i');

            if ($slot->ends_at) {
                $appointmentLabel .= ' – ' . $slot->ends_at->format('H:i');
            }
        }

        return (new MailMessage)
            ->subject('Rezervácia bola vytvorená')
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