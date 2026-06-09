<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CapacityWindow;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AdminBookingCalendarService
{
    public function getAvailableAdminSlots(Branch $branch): Collection
    {
        return collect();
    }

    public function getCalendarBookings(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        return Booking::query()
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
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Booking $booking) => $this->mapBooking($booking))
            ->values();
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
        ];
    }


    private function formatCalendarDateTime(?Carbon $value): ?string
    {
        return $value?->format('Y-m-d\TH:i:s');
    }

    private function isActiveBooking(Booking $booking): bool
    {
        return ! in_array($booking->status, ['cancelled', 'rejected', 'no_show'], true);
    }
}