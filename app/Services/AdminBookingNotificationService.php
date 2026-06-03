<?php

namespace App\Services;

use App\Models\Booking;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\BookingRescheduledNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class AdminBookingNotificationService
{
    public function sendCreatedNotification(Booking $booking): void
    {
        $booking->loadMissing(['branch', 'service', 'bookingSlot']);

        if (! filled($booking->patient_email)) {
            return;
        }

        Notification::route('mail', $booking->patient_email)
            ->notify(new BookingCreatedNotification($booking));
    }

    public function sendCancelledNotification(Booking $booking, ?string $reason = null): void
    {
        $booking->loadMissing(['branch', 'service', 'bookingSlot']);

        if (! filled($booking->patient_email)) {
            return;
        }

        Notification::route('mail', $booking->patient_email)
            ->notify(new BookingCancelledNotification($booking, $reason));
    }

    public function sendRescheduledNotification(
        Booking $booking,
        ?Carbon $oldStartsAt = null,
        ?Carbon $oldEndsAt = null,
        ?string $reason = null,
    ): void {
        $booking->loadMissing(['branch', 'service', 'bookingSlot']);

        if (! filled($booking->patient_email)) {
            return;
        }

        Notification::route('mail', $booking->patient_email)
            ->notify(new BookingRescheduledNotification(
                booking: $booking,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                reason: $reason,
            ));
    }
}
