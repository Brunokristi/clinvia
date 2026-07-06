<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\BookingCalendarInvite;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly Carbon $startsAt,
        private readonly ?Carbon $endsAt,
        private readonly bool $isRecurring,
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

        $appointmentLabel = $this->startsAt->format('d.m.Y H:i');

        if ($this->endsAt) {
            $appointmentLabel .= ' – ' . $this->endsAt->format('H:i');
        }

        $mail = (new MailMessage)
            ->subject('Pripomienka rezervácie na zajtra')
            ->view('emails.bookings.reminder', [
                'patientName' => $this->booking->patient_name,
                'serviceName' => $this->serviceName(),
                'branchName' => $this->booking->branch?->name ?? '—',
                'appointmentLabel' => $appointmentLabel,
                'isRecurring' => $this->isRecurring,
            ]);

        $mail->attachData(
            BookingCalendarInvite::buildIcs(
                booking: $this->booking,
                startsAt: $this->startsAt->copy(),
                endsAt: $this->endsAt?->copy(),
            ),
            'reservation-reminder.ics',
            ['mime' => 'text/calendar; charset=UTF-8; method=PUBLISH'],
        );

        return $mail;
    }

    private function serviceName(): string
    {
        if ($this->booking->services->isNotEmpty()) {
            return $this->booking->services
                ->pluck('name')
                ->join(', ');
        }

        return $this->booking->service?->name ?? '—';
    }
}
