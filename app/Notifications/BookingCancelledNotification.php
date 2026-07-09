<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\BookingCalendarInvite;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly ?string $reason = null,
        private readonly ?Carbon $appointmentStartsAt = null,
        private readonly ?Carbon $appointmentEndsAt = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['branch', 'service', 'services']);

        $startsAt = $this->appointmentStartsAt
            ?? $this->booking->starts_at;

        $endsAt = $this->appointmentEndsAt
            ?? $this->booking->ends_at;

        $appointmentLabel = null;

        if ($startsAt) {
            $appointmentLabel = $startsAt->format('d.m.Y')
                . ' o '
                . $startsAt->format('H:i');

            if ($endsAt) {
                $appointmentLabel .= ' – ' . $endsAt->format('H:i');
            }
        }

        $mail = (new MailMessage)
            ->subject('Rezervácia bola zrušená')
            ->view('emails.bookings.cancelled', [
                'patientName' => $this->booking->patient_name,
                'serviceName' => $this->booking->services->isNotEmpty()
                    ? $this->booking->services->pluck('name')->join(', ')
                    : ($this->booking->service?->name ?? '—'),
                'branchName' => $this->booking->branch?->name ?? '—',
                'appointmentLabel' => $appointmentLabel,
                'reason' => $this->reason,
            ]);

        if ($startsAt) {
            $mail->attachData(
                BookingCalendarInvite::buildIcs(
                    booking: $this->booking,
                    startsAt: $startsAt->copy(),
                    endsAt: $endsAt?->copy(),
                    method: 'CANCEL',
                    status: 'CANCELLED',
                    sequence: max(1, (int) ($this->booking->updated_at?->timestamp ?? now()->timestamp)),
                ),
                'reservation-cancelled.ics',
                ['mime' => 'text/calendar; charset=UTF-8; method=CANCEL'],
            );
        }

        return $mail;
    }
}