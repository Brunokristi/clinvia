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
        $this->booking->loadMissing(['branch', 'service', 'bookingSlot']);

        $slot = $this->booking->bookingSlot;
        $service = $this->booking->service;
        $branch = $this->booking->branch;

        $mail = (new MailMessage)
            ->subject('Rezervácia bola presunutá')
            ->greeting('Dobrý deň, ' . $this->booking->patient_name . ',')
            ->line('Vaša rezervácia bola presunutá.')
            ->line('Služba: ' . ($service?->name ?? '—'))
            ->line('Pobočka: ' . ($branch?->name ?? '—'));

        if ($this->oldStartsAt && $this->oldEndsAt) {
            $mail->line(
                'Pôvodný termín: '
                . $this->oldStartsAt->format('d.m.Y')
                . ' o '
                . $this->oldStartsAt->format('H:i')
                . ' - '
                . $this->oldEndsAt->format('H:i')
            );
        }

        if ($slot) {
            $mail->line(
                'Nový termín: '
                . $slot->starts_at->format('d.m.Y')
                . ' o '
                . $slot->starts_at->format('H:i')
                . ' - '
                . $slot->ends_at->format('H:i')
            );
        }

        if (filled($this->reason)) {
            $mail->line('Dôvod: ' . $this->reason);
        }

        return $mail
            ->line('V prípade otázok nás prosím kontaktujte.')
            ->salutation('Ďakujeme');
    }
}