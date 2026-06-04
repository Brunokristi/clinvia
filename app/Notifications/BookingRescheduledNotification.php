<?php

namespace App\Notifications;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRescheduledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly ?Carbon $oldStartsAt = null,
        private readonly ?Carbon $oldEndsAt = null,
        private readonly ?string $reason = null,
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
        ]);

        $slot = $this->booking->bookingSlot;

        $oldAppointmentLabel = null;

        if ($this->oldStartsAt && $this->oldEndsAt) {
            $oldAppointmentLabel = $this->oldStartsAt->format('d.m.Y')
                . ' o '
                . $this->oldStartsAt->format('H:i')
                . ' – '
                . $this->oldEndsAt->format('H:i');
        }

        $newAppointmentLabel = null;

        if ($slot?->starts_at && $slot?->ends_at) {
            $newAppointmentLabel = $slot->starts_at->format('d.m.Y')
                . ' o '
                . $slot->starts_at->format('H:i')
                . ' – '
                . $slot->ends_at->format('H:i');
        }

        return (new MailMessage)
            ->subject('Rezervácia bola upravená')
            ->view('emails.bookings.rescheduled', [
                'patientName' => $this->booking->patient_name,
                'serviceName' => $this->serviceName(),
                'branchName' => $this->booking->branch?->name ?? '—',
                'oldAppointmentLabel' => $oldAppointmentLabel,
                'newAppointmentLabel' => $newAppointmentLabel,
                'reason' => $this->reason,
            ]);
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