<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\BookingReminderNotification;
use App\Services\DisabledDayService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders {--date= : Target date in Y-m-d format (defaults to tomorrow)}';

    protected $description = 'Send reminder emails to patients one day before their booking';

    public function handle(RecurrenceService $recurrenceService, DisabledDayService $disabledDayService): int
    {
        $targetDate = $this->resolveTargetDate();
        $rangeStart = $targetDate->copy()->startOfDay();
        $rangeEnd = $targetDate->copy()->endOfDay();

        $baseQuery = Booking::query()
            ->with([
                'branch',
                'service',
                'services',
                'bookingSlot',
                'capacityWindow',
            ])
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->whereNotNull('patient_email');

        $sentCount = 0;

        $oneOffBookings = (clone $baseQuery)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->when(
                Schema::hasColumn('bookings', 'recurrence'),
                fn ($query) => $query->whereNull('recurrence'),
            )
            ->orderBy('starts_at')
            ->get();

        foreach ($oneOffBookings as $booking) {
            if (! $this->shouldSendReminderForOccurrence($booking, $booking->starts_at->copy())) {
                continue;
            }

            Notification::route('mail', $booking->patient_email)
                ->notify(new BookingReminderNotification(
                    booking: $booking,
                    startsAt: $booking->starts_at->copy(),
                    endsAt: $booking->ends_at?->copy(),
                    isRecurring: false,
                ));

            $sentCount++;
        }

        if (Schema::hasColumn('bookings', 'recurrence')) {
            $recurringBookings = (clone $baseQuery)
                ->whereNotNull('recurrence')
                ->where('starts_at', '<=', $rangeEnd)
                ->orderBy('starts_at')
                ->get();

            foreach ($recurringBookings as $booking) {
                $occurrenceDates = $recurrenceService->getOccurrenceDates(
                    seriesStart: $booking->starts_at->copy(),
                    rangeStart: $rangeStart,
                    rangeEnd: $rangeEnd,
                    recurrence: $booking->recurrence,
                    excludedDates: $booking->recurrence_excluded_dates ?? [],
                );

                foreach ($occurrenceDates as $occurrenceDate) {
                    if ($disabledDayService->isDisabled($booking->branch, $occurrenceDate)) {
                        continue;
                    }

                    $occurrenceStartsAt = Carbon::parse($occurrenceDate->toDateString() . ' ' . $booking->starts_at->format('H:i:s'));

                    $occurrenceEndsAt = $booking->ends_at
                        ? Carbon::parse($occurrenceDate->toDateString() . ' ' . $booking->ends_at->format('H:i:s'))
                        : null;

                    if (! $this->shouldSendReminderForOccurrence($booking, $occurrenceStartsAt)) {
                        continue;
                    }

                    Notification::route('mail', $booking->patient_email)
                        ->notify(new BookingReminderNotification(
                            booking: $booking,
                            startsAt: $occurrenceStartsAt,
                            endsAt: $occurrenceEndsAt,
                            isRecurring: true,
                        ));

                    $sentCount++;
                }
            }
        }

        $this->info(sprintf('Sent %d booking reminder(s) for %s.', $sentCount, $targetDate->toDateString()));

        return self::SUCCESS;
    }

    private function resolveTargetDate(): Carbon
    {
        $option = $this->option('date');

        if (filled($option)) {
            return Carbon::parse((string) $option)->startOfDay();
        }

        return now()->addDay()->startOfDay();
    }

    private function shouldSendReminderForOccurrence(Booking $booking, Carbon $occurrenceStartsAt): bool
    {
        if (blank($booking->patient_email)) {
            return false;
        }

        $cacheKey = sprintf('booking-reminder:%d:%s', $booking->id, $occurrenceStartsAt->toDateString());

        return Cache::add($cacheKey, true, now()->addDays(3));
    }
}
