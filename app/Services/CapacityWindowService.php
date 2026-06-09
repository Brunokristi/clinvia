<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CapacityWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CapacityWindowService
{
    public function cancelWindow(
        CapacityWindow $capacityWindow,
        bool $notifyPatient,
        ?string $reason,
        AdminBookingNotificationService $notificationService,
    ): void {
        DB::transaction(function () use ($capacityWindow, $notifyPatient, $reason, $notificationService): void {
            $lockedWindow = CapacityWindow::query()
                ->whereKey($capacityWindow->id)
                ->with('bookings')
                ->lockForUpdate()
                ->firstOrFail();

            $bookings = $lockedWindow->bookings()
                ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                $oldStatus = $booking->status;

                $booking->update([
                    'status' => 'cancelled',
                ]);

                if ($oldStatus !== 'cancelled' && $notifyPatient) {
                    $booking->refresh()->load(['branch', 'service', 'services', 'capacityWindow']);

                    $notificationService->sendCancelledNotification(
                        booking: $booking,
                        reason: $reason,
                    );
                }
            }

            $lockedWindow->update([
                'status' => 'cancelled',
            ]);
        });
    }

    public function rescheduleWindow(
        CapacityWindow $capacityWindow,
        Carbon $newStartsAt,
        Carbon $newEndsAt,
        bool $notifyPatient,
        ?string $reason,
        AdminBookingNotificationService $notificationService,
    ): CapacityWindow {
        return DB::transaction(function () use (
            $capacityWindow,
            $newStartsAt,
            $newEndsAt,
            $notifyPatient,
            $reason,
            $notificationService,
        ): CapacityWindow {
            $lockedWindow = CapacityWindow::query()
                ->whereKey($capacityWindow->id)
                ->with(['bookings', 'service'])
                ->lockForUpdate()
                ->firstOrFail();

            $oldStartsAt = $lockedWindow->starts_at?->copy();
            $oldEndsAt = $lockedWindow->ends_at?->copy();

            $lockedWindow->update([
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
                'status' => 'active',
            ]);

            $bookings = $lockedWindow->bookings()
                ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                $booking->update([
                    'starts_at' => $newStartsAt,
                    'ends_at' => $newEndsAt,
                    'status' => 'confirmed',
                ]);

                if ($notifyPatient) {
                    $booking->refresh()->load(['branch', 'service', 'services', 'capacityWindow']);

                    $notificationService->sendRescheduledNotification(
                        booking: $booking,
                        oldStartsAt: $oldStartsAt,
                        oldEndsAt: $oldEndsAt,
                        reason: $reason,
                    );
                }
            }

            return $lockedWindow->refresh();
        });
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
            ->map(fn (CapacityWindow $window) => $this->mapCapacityWindow($window))
            ->values();
    }

    private function mapCapacityWindow(CapacityWindow $window): array
    {
        $activeBookings = $window->bookings
            ->filter(fn (Booking $booking) => $booking->isActive())
            ->values();

        return [
            'id' => $window->id,
            'capacity_window_id' => $window->id,
            'series_uuid' => $window->series_uuid,
            'service_id' => $window->service_id,
            'service_name' => $window->service?->name,
            'date' => $window->starts_at?->toDateString(),
            'starts_at' => $window->starts_at?->toIso8601String(),
            'ends_at' => $window->ends_at?->toIso8601String(),
            'starts_datetime' => $window->starts_at?->toIso8601String(),
            'ends_datetime' => $window->ends_at?->toIso8601String(),
            'capacity' => (int) $window->capacity,
            'booked_count' => $activeBookings->count(),
            'available_count' => max(0, (int) $window->capacity - $activeBookings->count()),
            'status' => $window->status,
            'admin_note' => $window->admin_note,
            'bookings' => $activeBookings
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'capacity_window_id' => $booking->capacity_window_id,
                    'service_id' => $booking->service_id,
                    'service_name' => $booking->service?->name,
                    'patient_name' => $booking->patient_name,
                    'patient_email' => $booking->patient_email,
                    'patient_phone' => $booking->patient_phone,
                    'status' => $booking->status,
                    'patient_note' => $booking->patient_note,
                    'admin_note' => $booking->admin_note,
                    'starts_at' => $booking->starts_at?->toIso8601String(),
                    'ends_at' => $booking->ends_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
