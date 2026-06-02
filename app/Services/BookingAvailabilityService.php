<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BookingSlot;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingAvailabilityService
{
    public function getAvailableSlots(Branch $branch, Service $service, Carbon $date): Collection
    {
        if (! $service->is_bookable) {
            return collect();
        }

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        return BookingSlot::query()
            ->withCount('confirmedBookings')
            ->where('branch_id', $branch->id)
            ->where('service_id', $service->id)
            ->where('is_enabled', true)
            ->whereBetween('starts_at', [$start, $end])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (BookingSlot $slot) => $this->isSlotAvailable($slot));
    }

    public function isSlotAvailable(BookingSlot $slot): bool
    {
        return $slot->is_enabled
            && $slot->starts_at->isFuture()
            && $slot->confirmed_bookings_count < $slot->capacity;
    }
}