<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Services\EmailNotificationService;
use App\Services\DisabledDayService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders {--date= : Target date in Y-m-d format (defaults to tomorrow)}';

    protected $description = 'Send reminder emails to patients/participants one day before their booking or group event';

    public function handle(
        RecurrenceService $recurrenceService,
        DisabledDayService $disabledDayService,
        EmailNotificationService $emailNotificationService,
    ): int
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

            $emailNotificationService->dispatch('reminder.booking_tomorrow', [
                'event' => $event,
                'starts_at' => $bookingPayload->starts_at->copy(),
                'ends_at' => $bookingPayload->ends_at?->copy(),
            ]);

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

                $emailNotificationService->dispatch('reminder.booking_tomorrow', [
                    'event' => $event,
                    'starts_at' => $occurrenceStartsAt,
                    'ends_at' => $occurrenceEndsAt,
                ]);

                $sentCount++;
            }
        }

        $groupEvents = Event::query()
            ->with(['branch', 'groupDetail', 'participants'])
            ->where('type', EventType::GroupEvent->value)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->orderBy('starts_at')
            ->get();

        foreach ($groupEvents as $groupEvent) {
            foreach ($groupEvent->participants->where('status', 'confirmed') as $participant) {
                $emailNotificationService->dispatch('reminder.group_event_tomorrow', [
                    'event' => $groupEvent,
                    'participant' => $participant,
                    'starts_at' => $groupEvent->starts_at?->copy(),
                    'ends_at' => $groupEvent->ends_at?->copy(),
                ]);

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
