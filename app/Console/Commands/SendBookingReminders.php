<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Notifications\BookingReminderNotification;
use App\Services\DisabledDayService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders {--date= : Target date in Y-m-d format (defaults to tomorrow)}';

    protected $description = 'Send reminder emails to patients one day before their booking';

    public function handle(RecurrenceService $recurrenceService, DisabledDayService $disabledDayService): int
    {
        $targetDate = $this->resolveTargetDate();
        $rangeStart = $targetDate->copy()->startOfDay();
        $rangeEnd = $targetDate->copy()->endOfDay();

        $baseQuery = Event::query()
            ->with([
                'bookingDetail',
                'branch',
                'services',
            ])
            ->where('type', EventType::Booking->value)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->whereHas('bookingDetail', fn ($query) => $query->whereNotNull('patient_email'));

        $sentCount = 0;

        $oneOffEvents = (clone $baseQuery)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->where(function ($query) {
                $query
                    ->where('is_recurring', false)
                    ->orWhereNull('is_recurring');
            })
            ->orderBy('starts_at')
            ->get();

        foreach ($oneOffEvents as $event) {
            $bookingPayload = $this->toLegacyBookingPayload($event);

            if (! $bookingPayload || ! $bookingPayload->starts_at instanceof Carbon) {
                continue;
            }

            if (! $this->shouldSendReminderForOccurrence($event, $bookingPayload->starts_at->copy())) {
                continue;
            }

            Notification::route('mail', $bookingPayload->patient_email)
                ->notify(new BookingReminderNotification(
                    booking: $bookingPayload,
                    startsAt: $bookingPayload->starts_at->copy(),
                    endsAt: $bookingPayload->ends_at?->copy(),
                    isRecurring: false,
                ));

            $sentCount++;
        }

        $recurringEvents = (clone $baseQuery)
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_rule')
            ->where('starts_at', '<=', $rangeEnd)
            ->orderBy('starts_at')
            ->get();

        foreach ($recurringEvents as $event) {
            $bookingPayload = $this->toLegacyBookingPayload($event);

            if (! $bookingPayload || ! $bookingPayload->starts_at instanceof Carbon) {
                continue;
            }

            $occurrenceDates = $recurrenceService->getOccurrenceDates(
                seriesStart: $bookingPayload->starts_at->copy(),
                rangeStart: $rangeStart,
                rangeEnd: $rangeEnd,
                recurrence: $event->recurrence_rule ?? [],
                excludedDates: collect(data_get($event->metadata, 'recurrence_excluded_dates', []))
                    ->filter()
                    ->values()
                    ->all(),
            );

            foreach ($occurrenceDates as $occurrenceDate) {
                if ($disabledDayService->isDisabled($event->branch, $occurrenceDate)) {
                    continue;
                }

                $occurrenceStartsAt = Carbon::parse($occurrenceDate->toDateString() . ' ' . $bookingPayload->starts_at->format('H:i:s'));

                $occurrenceEndsAt = $bookingPayload->ends_at
                    ? Carbon::parse($occurrenceDate->toDateString() . ' ' . $bookingPayload->ends_at->format('H:i:s'))
                    : null;

                if (! $this->shouldSendReminderForOccurrence($event, $occurrenceStartsAt)) {
                    continue;
                }

                Notification::route('mail', $bookingPayload->patient_email)
                    ->notify(new BookingReminderNotification(
                        booking: $bookingPayload,
                        startsAt: $occurrenceStartsAt,
                        endsAt: $occurrenceEndsAt,
                        isRecurring: true,
                    ));

                $sentCount++;
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

    private function shouldSendReminderForOccurrence(Event $event, Carbon $occurrenceStartsAt): bool
    {
        if (blank($event->bookingDetail?->patient_email)) {
            return false;
        }

        $cacheKey = sprintf('booking-reminder:%d:%s', $event->id, $occurrenceStartsAt->toDateString());

        return Cache::add($cacheKey, true, now()->addDays(3));
    }

    private function toLegacyBookingPayload(Event $event): ?Booking
    {
        if (! $event->bookingDetail || ! $event->starts_at) {
            return null;
        }

        $legacyBooking = new Booking();

        $legacyBooking->id = $event->id;
        $legacyBooking->branch_id = $event->branch_id;
        $legacyBooking->starts_at = $event->starts_at;
        $legacyBooking->ends_at = $event->ends_at;
        $legacyBooking->patient_name = $event->bookingDetail->patient_name;
        $legacyBooking->patient_email = $event->bookingDetail->patient_email;
        $legacyBooking->patient_phone = $event->bookingDetail->patient_phone;
        $legacyBooking->patient_birth_number = $event->bookingDetail->patient_birth_number;
        $legacyBooking->patient_note = $event->bookingDetail->public_notes;
        $legacyBooking->admin_note = $event->bookingDetail->internal_notes;
        $legacyBooking->status = $event->status;
        $legacyBooking->service_id = $event->services->first()?->id;
        $legacyBooking->recurrence = $event->recurrence_rule;
        $legacyBooking->recurrence_excluded_dates = collect(data_get($event->metadata, 'recurrence_excluded_dates', []))
            ->filter()
            ->values()
            ->all();

        $legacyBooking->setRelation('branch', $event->branch);
        $legacyBooking->setRelation('service', $event->services->first());
        $legacyBooking->setRelation('services', $event->services);

        return $legacyBooking;
    }
}
