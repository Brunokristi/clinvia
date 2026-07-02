<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CapacityWindow;
use App\Models\Service;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminBookingCalendarService
{
    public function __construct(private RecurrenceService $recurrenceService)
    {
    }

    public function getAvailableAdminSlots(Branch $branch): Collection
    {
        return collect();
    }

    public function getCalendarBookings(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $supportsRecurringBookings = $this->supportsRecurringBookings();

        $oneOffBookingsQuery = Booking::query()
            ->with([
                'service',
                'services',
                'capacityWindow',
            ])
            ->where('branch_id', $branch->id)
            ->whereNull('capacity_window_id')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->orderBy('starts_at');

        if ($supportsRecurringBookings) {
            $oneOffBookingsQuery->where(function ($query) {
                $query->whereNull('recurrence');
            });
        }

        $oneOffBookings = $oneOffBookingsQuery
            ->get()
            ->map(fn (Booking $booking) => $this->mapBooking($booking))
            ->values();

        if (! $supportsRecurringBookings) {
            return $oneOffBookings;
        }

        $recurringBookings = Booking::query()
            ->with([
                'service',
                'services',
                'capacityWindow',
            ])
            ->where('branch_id', $branch->id)
            ->whereNull('capacity_window_id')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereNotNull('recurrence')
            ->where('starts_at', '<=', $rangeEnd)
            ->orderBy('starts_at')
            ->get()
            ->flatMap(function (Booking $booking) use ($rangeStart, $rangeEnd) {
                return $this->mapRecurringBookingOccurrences($booking, $rangeStart, $rangeEnd);
            })
            ->values();

        return $oneOffBookings->concat($recurringBookings)->values();
    }

    public function getCalendarCapacityWindows(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        return CapacityWindow::query()
            ->with([
                'service',
                'bookings.service',
                'bookings.services',
            ])
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CapacityWindow $capacityWindow) => $this->mapCapacityWindow($capacityWindow))
            ->values();
    }

    private function mapCapacityWindow(CapacityWindow $capacityWindow): array
    {
        $activeBookings = $capacityWindow->bookings
            ->filter(fn (Booking $booking) => $this->isActiveBooking($booking))
            ->values();

        return [
            'id' => $capacityWindow->id,
            'capacity_window_id' => $capacityWindow->id,
            'series_uuid' => $capacityWindow->series_uuid,

            'service_id' => $capacityWindow->service_id,
            'service_name' => $capacityWindow->service?->name,

            'date' => $capacityWindow->starts_at?->toDateString(),

            'starts_at' => $this->formatCalendarDateTime($capacityWindow->starts_at),
            'ends_at' => $this->formatCalendarDateTime($capacityWindow->ends_at),

            'starts_datetime' => $this->formatCalendarDateTime($capacityWindow->starts_at),
            'ends_datetime' => $this->formatCalendarDateTime($capacityWindow->ends_at),

            'capacity' => (int) $capacityWindow->capacity,
            'booked_count' => $activeBookings->count(),
            'available_count' => max(0, (int) $capacityWindow->capacity - $activeBookings->count()),

            'status' => $capacityWindow->status,
            'admin_note' => $capacityWindow->admin_note,

            'bookings' => $activeBookings
                ->map(fn (Booking $booking) => $this->mapBooking($booking))
                ->values()
                ->all(),
        ];
    }

    private function mapBooking(Booking $booking): array
    {
        $bookingServices = $booking->services->isNotEmpty()
            ? $booking->services
            : collect($booking->service ? [$booking->service] : []);

        return [
            'id' => $booking->id,
            'calendar_event_id' => sprintf('booking-%s', $booking->id),

            // Keep this temporarily for old dialogs/components.
            // New logic should not depend on booking_slot_id.
            'booking_slot_id' => $booking->booking_slot_id,

            'capacity_window_id' => $booking->capacity_window_id,
            'service_id' => $booking->service_id,

            'service_ids' => $bookingServices
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),

            'service_name' => $bookingServices->isNotEmpty()
                ? $bookingServices->pluck('name')->join(', ')
                : ($booking->service?->name ?? '—'),

            'services' => $bookingServices
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                ])
                ->values()
                ->all(),

            'patient_name' => $booking->patient_name,
            'patient_email' => $booking->patient_email,
            'patient_phone' => $booking->patient_phone,

            'date' => $booking->starts_at?->toDateString(),

            'starts_at' => $this->formatCalendarDateTime($booking->starts_at),
            'ends_at' => $this->formatCalendarDateTime($booking->ends_at),

            'starts_datetime' => $this->formatCalendarDateTime($booking->starts_at),
            'ends_datetime' => $this->formatCalendarDateTime($booking->ends_at),

            'status' => $booking->status,
            'patient_note' => $booking->patient_note,
            'admin_note' => $booking->admin_note,
            'series_uuid' => $booking->series_uuid,
            'recurrence' => $booking->recurrence,
            'recurrence_excluded_dates' => $booking->recurrence_excluded_dates ?? [],
        ];
    }

    private function mapRecurringBookingOccurrences(Booking $booking, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        if (! $booking->recurrence) {
            return collect();
        }

        $occurrenceDates = $this->recurrenceService->getOccurrenceDates(
            seriesStart: $booking->starts_at->copy(),
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
            recurrence: $booking->recurrence,
            excludedDates: $booking->recurrence_excluded_dates ?? [],
        );

        return $occurrenceDates->map(function (Carbon $occurrenceDate) use ($booking) {
            $startsAt = Carbon::parse($occurrenceDate->toDateString() . ' ' . $booking->starts_at->format('H:i:s'));
            $endsAt = Carbon::parse($occurrenceDate->toDateString() . ' ' . $booking->ends_at->format('H:i:s'));

            return [
                ...$this->mapBooking($booking),
                'calendar_event_id' => sprintf('booking-%s-%s', $booking->id, $occurrenceDate->toDateString()),
                'starts_at' => $this->formatCalendarDateTime($startsAt),
                'ends_at' => $this->formatCalendarDateTime($endsAt),
                'starts_datetime' => $this->formatCalendarDateTime($startsAt),
                'ends_datetime' => $this->formatCalendarDateTime($endsAt),
                'date' => $occurrenceDate->toDateString(),
                'occurrence_date' => $occurrenceDate->toDateString(),
                'is_recurring' => true,
            ];
        });
    }


    private function formatCalendarDateTime(?Carbon $value): ?string
    {
        return $value?->format('Y-m-d\TH:i:s');
    }

    private function isActiveBooking(Booking $booking): bool
    {
        return ! in_array($booking->status, ['cancelled', 'rejected', 'no_show'], true);
    }

    private function supportsRecurringBookings(): bool
    {
        return Schema::hasColumn('bookings', 'series_uuid')
            && Schema::hasColumn('bookings', 'recurrence');
    }
}