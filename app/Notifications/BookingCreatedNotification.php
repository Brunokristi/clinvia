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
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->booking->loadMissing(['branch', 'service', 'bookingSlot']);

        $slot = $this->booking->bookingSlot;

        return (new MailMessage)
            ->subject('Rezervácia bola vytvorená')
            ->greeting('Dobrý deň ' . $this->booking->patient_name . ',')
            ->line('vaša rezervácia bola vytvorená.')
            ->line('Služba: ' . ($this->booking->service?->name ?? '—'))
            ->line('Termín: ' . ($slot?->starts_at?->format('d.m.Y H:i') ?? '—') . ($slot?->ends_at ? ' – ' . $slot->ends_at->format('H:i') : ''))
            ->when(filled($this->booking->patient_note), function (MailMessage $message) {
                return $message->line('Správa: ' . $this->booking->patient_note);
            })
            ->line('Ďakujeme.');
    }
}