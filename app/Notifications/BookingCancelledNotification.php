<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['branch', 'service', 'bookingSlot']);

        $slot = $this->booking->bookingSlot;

        $appointmentLabel = null;

        if ($slot?->starts_at && $slot?->ends_at) {
            $appointmentLabel = $slot->starts_at->format('d.m.Y')
                . ' o '
                . $slot->starts_at->format('H:i')
                . ' – '
                . $slot->ends_at->format('H:i');
        }

        return (new MailMessage)
            ->subject('Rezervácia bola zrušená')
            ->view('emails.bookings.cancelled', [
                'patientName' => $this->booking->patient_name,
                'serviceName' => $this->booking->service?->name ?? '—',
                'branchName' => $this->booking->branch?->name ?? '—',
                'appointmentLabel' => $appointmentLabel,
                'reason' => $this->reason,
            ]);
    }
}