<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\BookingCalendarInvite;
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
        ]);

        $startsAt = $this->booking->starts_at;
        $endsAt = $this->booking->ends_at;

        $appointmentLabel = '—';

        if ($startsAt) {
            $appointmentLabel = $startsAt->format('d.m.Y H:i');

            if ($endsAt) {
                $appointmentLabel .= ' – ' . $endsAt->format('H:i');
            }
        }

        $isRecurring = ! empty($this->booking->recurrence);

        $mail = (new MailMessage)
            ->subject('Nová rezervácia')
            ->view('emails.bookings.created', [
                'patientName' => $this->booking->patient_name,
                'serviceName' => $this->booking->services->isNotEmpty()
                    ? $this->booking->services->pluck('name')->join(', ')
                    : ($this->booking->service?->name ?? '—'),
                'branchName' => $this->booking->branch?->name ?? '—',
                'appointmentLabel' => $appointmentLabel,
                'patientNote' => $this->booking->patient_note,
                'isRecurring' => $isRecurring,
            ]);

        if ($startsAt) {
            $mail->attachData(
                BookingCalendarInvite::buildIcs(
                    booking: $this->booking,
                    startsAt: $startsAt->copy(),
                    endsAt: $endsAt?->copy(),
                    method: 'REQUEST',
                    status: 'CONFIRMED',
                    sequence: max(0, (int) ($this->booking->updated_at?->timestamp ?? 0)),
                ),
                'reservation.ics',
                ['mime' => 'text/calendar; charset=UTF-8; method=REQUEST'],
            );
        }

        return $mail;
    }
}