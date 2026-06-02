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
        $service = $this->booking->service;
        $branch = $this->booking->branch;

        $date = $slot?->starts_at?->format('d.m.Y');
        $time = $slot?->starts_at?->format('H:i') . ' - ' . $slot?->ends_at?->format('H:i');

        $mail = (new MailMessage)
            ->subject('Rezervácia bola zrušená')
            ->greeting('Dobrý deň, ' . $this->booking->patient_name . ',')
            ->line('Vaša rezervácia bola zrušená.')
            ->line('Služba: ' . ($service?->name ?? '—'))
            ->line('Pobočka: ' . ($branch?->name ?? '—'));

        if ($date && $time) {
            $mail->line('Pôvodný termín: ' . $date . ' o ' . $time);
        }

        if (filled($this->reason)) {
            $mail->line('Dôvod: ' . $this->reason);
        }

        return $mail
            ->line('V prípade otázok nás prosím kontaktujte.')
            ->salutation('Ďakujeme');
    }
}