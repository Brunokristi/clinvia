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
        return $this->getAvailableSlotsForServices(
            branch: $branch,
            services: collect([$service]),
            date: $date,
        );
    }

    public function getAvailableSlotsForServices(Branch $branch, Collection $services, Carbon $date): Collection
    {
        $services = $services
            ->filter(fn (Service $service) => $service->is_bookable)
            ->values();

        if ($services->isEmpty()) {
            return collect();
        }

        $selectedServiceIds = $services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $slots = BookingSlot::query()
            ->withCount('confirmedBookings')
            ->where('branch_id', $branch->id)
            ->whereIn('service_id', $selectedServiceIds)
            ->where('is_enabled', true)
            ->whereBetween('starts_at', [$start, $end])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (BookingSlot $slot) => $this->isSlotAvailable($slot))
            ->values();

        return $slots
            ->groupBy(function (BookingSlot $slot) {
                return $slot->starts_at->toDateTimeString() . '|' . $slot->ends_at->toDateTimeString();
            })
            ->filter(function (Collection $group) use ($selectedServiceIds) {
                $groupServiceIds = $group
                    ->pluck('service_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                return $selectedServiceIds
                    ->diff($groupServiceIds)
                    ->isEmpty();
            })
            ->map(function (Collection $group) use ($selectedServiceIds) {
                $primarySlot = $group
                    ->firstWhere('service_id', $selectedServiceIds->first())
                    ?? $group->first();

                $primarySlot->selected_service_ids = $selectedServiceIds->all();

                return $primarySlot;
            })
            ->sortBy('starts_at')
            ->values();
    }

    public function isSlotAvailable(BookingSlot $slot): bool
    {
        return $slot->is_enabled
            && $slot->starts_at->isFuture()
            && $slot->confirmed_bookings_count < $slot->capacity;
    }
}