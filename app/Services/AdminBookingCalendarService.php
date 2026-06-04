<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\BookingSlot;
use App\Models\Branch;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdminBookingCalendarService
{
    public function getAvailableAdminSlots(Branch $branch): Collection
    {
        return BookingSlot::query()
            ->with('service')
            ->where('branch_id', $branch->id)
            ->where('starts_at', '>=', now())
            ->where('is_enabled', true)
            ->orderBy('starts_at')
            ->get()
            ->map(function (BookingSlot $slot) {
                return [
                    'id' => $slot->id,
                    'service_id' => $slot->service_id,
                    'service_name' => $slot->service?->name,
                    'starts_at' => $slot->starts_at->toDateTimeString(),
                    'ends_at' => $slot->ends_at->toDateTimeString(),
                    'label' => $slot->starts_at->format('d.m.Y H:i')
                        . ' - '
                        . $slot->ends_at->format('H:i')
                        . ' · '
                        . ($slot->service?->name ?? 'Služba'),
                ];
            })
            ->values();
    }

    public function getCalendarBookings(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $allBookings = Booking::query()
            ->with([
                'service',
                'services',
                'bookingSlot.service',
            ])
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereHas('bookingSlot', function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->where('starts_at', '<', $rangeEnd)
                    ->where('ends_at', '>', $rangeStart);
            })
            ->orderByDesc('created_at')
            ->get();

        $capacityBookingIds = $this->getCapacityBookingIds($branch, $allBookings, $rangeStart, $rangeEnd);

        return $allBookings
            ->reject(fn (Booking $booking) => $capacityBookingIds->contains($booking->id))
            ->map(fn (Booking $booking) => $this->mapBooking($booking))
            ->values();
    }

    public function getCalendarCapacityWindows(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $allBookings = Booking::query()
            ->with([
                'service',
                'services',
                'bookingSlot.service',
            ])
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereHas('bookingSlot', function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->where('starts_at', '<', $rangeEnd)
                    ->where('ends_at', '>', $rangeStart);
            })
            ->orderByDesc('created_at')
            ->get();

        $capacityRules = BookingAvailabilityRule::query()
            ->with(['services'])
            ->where('branch_id', $branch->id)
            ->where('slot_mode', 'single_service_many_clients')
            ->where('is_enabled', true)
            ->get();

        return $capacityRules
            ->flatMap(function (BookingAvailabilityRule $rule) use ($rangeStart, $rangeEnd, $allBookings) {
                $ruleDates = $this->getRuleDatesInRange($rule, $rangeStart, $rangeEnd);

                return collect($ruleDates)->map(function (Carbon $ruleDate) use ($rule, $allBookings) {
                    $windowStart = Carbon::parse($ruleDate->toDateString() . ' ' . $rule->starts_at);
                    $windowEnd = Carbon::parse($ruleDate->toDateString() . ' ' . $rule->ends_at);

                    $serviceIds = $this->getRuleServiceIds($rule);

                    $bookings = $allBookings
                        ->filter(function (Booking $booking) use ($serviceIds, $windowStart, $windowEnd) {
                            if (! $booking->bookingSlot) {
                                return false;
                            }

                            if (! $this->bookingBelongsToAnyService($booking, $serviceIds)) {
                                return false;
                            }

                            return $booking->bookingSlot->starts_at->lt($windowEnd)
                                && $booking->bookingSlot->ends_at->gt($windowStart);
                        })
                        ->values();

                    $service = $rule->services->first();

                    if (! $service && $rule->service_id) {
                        $service = Service::query()->find($rule->service_id);
                    }

                    return [
                        'id' => $rule->id . '-' . $ruleDate->toDateString(),
                        'rule_id' => $rule->id,
                        'service_id' => $rule->service_id,
                        'service_ids' => $serviceIds->values(),
                        'service_name' => $rule->services->isNotEmpty()
                            ? $rule->services->pluck('name')->join(', ')
                            : $service?->name,
                        'date' => $ruleDate->toDateString(),
                        'starts_at' => $windowStart->toDateTimeString(),
                        'ends_at' => $windowEnd->toDateTimeString(),
                        'starts_time' => $windowStart->format('H:i'),
                        'ends_time' => $windowEnd->format('H:i'),
                        'capacity' => $rule->bookable_places,
                        'bookings' => $bookings
                            ->map(fn (Booking $booking) => $this->mapBooking($booking))
                            ->values(),
                    ];
                });
            })
            ->values();
    }

    public function getRuleDatesInRange(BookingAvailabilityRule $rule, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if (! $rule->date) {
            return [];
        }

        $startDate = Carbon::parse($rule->date)->startOfDay();

        $excludedDates = collect($rule->excluded_dates ?? [])
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $repeatEndsOn = $rule->repeat_ends_on
            ? Carbon::parse($rule->repeat_ends_on)->endOfDay()
            : null;

        if ($repeatEndsOn && $repeatEndsOn->lt($rangeStart)) {
            return [];
        }

        $effectiveRangeEnd = $repeatEndsOn && $repeatEndsOn->lt($rangeEnd)
            ? $repeatEndsOn
            : $rangeEnd;

        if (! $rule->repeats) {
            $dateString = $startDate->toDateString();

            if (
                $startDate->betweenIncluded($rangeStart, $effectiveRangeEnd)
                && ! in_array($dateString, $excludedDates, true)
            ) {
                return [$startDate];
            }

            return [];
        }

        $dates = [];
        $repeatEvery = max((int) $rule->repeat_every, 1);
        $repeatUnit = $rule->repeat_unit ?? 'weeks';
        $cursor = $startDate->copy();

        while ($cursor->lt($rangeStart)) {
            match ($repeatUnit) {
                'days' => $cursor->addDays($repeatEvery),
                'months' => $cursor->addMonths($repeatEvery),
                default => $cursor->addWeeks($repeatEvery),
            };
        }

        while ($cursor->lte($effectiveRangeEnd)) {
            $dateString = $cursor->toDateString();

            if (! in_array($dateString, $excludedDates, true)) {
                $dates[] = $cursor->copy();
            }

            match ($repeatUnit) {
                'days' => $cursor->addDays($repeatEvery),
                'months' => $cursor->addMonths($repeatEvery),
                default => $cursor->addWeeks($repeatEvery),
            };
        }

        return $dates;
    }

    public function getCapacityWindowBookingsForDate(Branch $branch, BookingAvailabilityRule $rule, Carbon $date): Collection
    {
        $windowStart = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
        $windowEnd = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);
        $serviceIds = $this->getRuleServiceIds($rule);

        return Booking::query()
            ->with([
                'branch',
                'service',
                'services',
                'bookingSlot',
            ])
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereHas('bookingSlot', function ($query) use ($windowStart, $windowEnd) {
                $query
                    ->where('starts_at', '<', $windowEnd)
                    ->where('ends_at', '>', $windowStart);
            })
            ->get()
            ->filter(fn (Booking $booking) => $this->bookingBelongsToAnyService($booking, $serviceIds))
            ->values();
    }

    public function getCapacityWindowBookingsFromDate(Branch $branch, BookingAvailabilityRule $rule, Carbon $date): Collection
    {
        $serviceIds = $this->getRuleServiceIds($rule);

        return Booking::query()
            ->with([
                'branch',
                'service',
                'services',
                'bookingSlot',
            ])
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereHas('bookingSlot', function ($query) use ($date) {
                $query->where('starts_at', '>=', $date);
            })
            ->get()
            ->filter(fn (Booking $booking) => $this->bookingBelongsToAnyService($booking, $serviceIds))
            ->values();
    }

    public function moveCapacityWindowOccurrence(
        Branch $branch,
        BookingAvailabilityRule $rule,
        Carbon $oldDate,
        Carbon $newStartsAt,
        Carbon $newEndsAt,
        int $serviceId,
    ): BookingAvailabilityRule {
        $newDate = $newStartsAt->toDateString();

        if (! $rule->repeats) {
            $rule->update([
                'date' => $newDate,
                'day_of_week' => $newStartsAt->dayOfWeekIso,
                'starts_at' => $newStartsAt->format('H:i'),
                'ends_at' => $newEndsAt->format('H:i'),
            ]);

            return $rule->fresh(['services']);
        }

        $excludedDates = $rule->excluded_dates ?? [];
        $oldDateString = $oldDate->toDateString();

        if (! in_array($oldDateString, $excludedDates, true)) {
            $excludedDates[] = $oldDateString;
        }

        sort($excludedDates);

        $rule->update([
            'excluded_dates' => $excludedDates,
        ]);

        $newRule = BookingAvailabilityRule::create([
            'branch_id' => $branch->id,
            'day_of_week' => $newStartsAt->dayOfWeekIso,
            'date' => $newDate,
            'starts_at' => $newStartsAt->format('H:i'),
            'ends_at' => $newEndsAt->format('H:i'),
            'slot_mode' => 'single_service_many_clients',
            'service_id' => $serviceId,
            'service_ids' => [$serviceId],
            'bookable_places' => $rule->bookable_places,
            'repeats' => false,
            'repeat_every' => 1,
            'repeat_unit' => 'weeks',
            'repeat_ends_on' => null,
            'excluded_dates' => [],
            'is_enabled' => true,
        ]);

        $newRule->services()->sync([$serviceId]);

        return $newRule->fresh(['services']);
    }

    public function resolveSlotForAdminBooking(Branch $branch, array $data): BookingSlot
    {
        if (! empty($data['booking_slot_id'])) {
            $slot = BookingSlot::query()
                ->where('branch_id', $branch->id)
                ->whereKey($data['booking_slot_id'])
                ->firstOrFail();

            if (! $slot->is_enabled) {
                $slot->update([
                    'is_enabled' => true,
                ]);
            }

            return $slot;
        }

        $serviceIds = collect($data['service_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($serviceIds->isEmpty() && ! empty($data['service_id'])) {
            $serviceIds = collect([
                (int) $data['service_id'],
            ]);
        }

        if ($serviceIds->isEmpty() || empty($data['starts_at']) || empty($data['ends_at'])) {
            throw ValidationException::withMessages([
                'starts_at' => 'Vyberte službu, začiatok a koniec rezervácie.',
            ]);
        }

        $services = Service::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->whereIn('id', $serviceIds)
            ->get()
            ->sortBy(fn (Service $service) => $serviceIds->search((int) $service->id))
            ->values();

        if ($services->isEmpty()) {
            throw ValidationException::withMessages([
                'service_ids' => 'Vybrané služby nie sú dostupné.',
            ]);
        }

        $mainService = $services->first();

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Koniec rezervácie musí byť po začiatku.',
            ]);
        }

        $slot = BookingSlot::firstOrCreate(
            [
                'branch_id' => $branch->id,
                'service_id' => $mainService->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ],
            [
                'capacity' => max(1, (int) ($mainService->capacity ?? 1)),
                'is_enabled' => true,
            ],
        );

        if (! $slot->is_enabled) {
            $slot->update([
                'is_enabled' => true,
            ]);
        }

        return $slot;
    }

    public function createOrEnableCapacitySlot(
        Branch $branch,
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        int $capacity,
    ): BookingSlot {
        $slot = BookingSlot::firstOrCreate(
            [
                'branch_id' => $branch->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ],
            [
                'capacity' => max(1, $capacity),
                'is_enabled' => true,
            ],
        );

        if (! $slot->is_enabled) {
            $slot->update([
                'capacity' => max(1, $capacity),
                'is_enabled' => true,
            ]);
        }

        return $slot;
    }

    private function getCapacityBookingIds(Branch $branch, Collection $allBookings, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $capacityRules = BookingAvailabilityRule::query()
            ->with(['services'])
            ->where('branch_id', $branch->id)
            ->where('slot_mode', 'single_service_many_clients')
            ->where('is_enabled', true)
            ->get();

        return $capacityRules
            ->flatMap(function (BookingAvailabilityRule $rule) use ($rangeStart, $rangeEnd, $allBookings) {
                return collect($this->getRuleDatesInRange($rule, $rangeStart, $rangeEnd))
                    ->flatMap(function (Carbon $ruleDate) use ($rule, $allBookings) {
                        $windowStart = Carbon::parse($ruleDate->toDateString() . ' ' . $rule->starts_at);
                        $windowEnd = Carbon::parse($ruleDate->toDateString() . ' ' . $rule->ends_at);
                        $serviceIds = $this->getRuleServiceIds($rule);

                        return $allBookings
                            ->filter(function (Booking $booking) use ($serviceIds, $windowStart, $windowEnd) {
                                if (! $booking->bookingSlot) {
                                    return false;
                                }

                                if (! $this->bookingBelongsToAnyService($booking, $serviceIds)) {
                                    return false;
                                }

                                return $booking->bookingSlot->starts_at->lt($windowEnd)
                                    && $booking->bookingSlot->ends_at->gt($windowStart);
                            })
                            ->pluck('id');
                    });
            })
            ->unique()
            ->values();
    }

    private function mapBooking(Booking $booking): array
    {
        $bookingServices = $booking->services->isNotEmpty()
            ? $booking->services
            : collect($booking->service ? [$booking->service] : []);

        return [
            'id' => $booking->id,
            'booking_slot_id' => $booking->booking_slot_id,
            'service_id' => $booking->service_id,
            'service_ids' => $bookingServices
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'service_name' => $bookingServices->isNotEmpty()
                ? $bookingServices->pluck('name')->join(', ')
                : ($booking->bookingSlot?->service?->name ?? '—'),
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
            'starts_at' => $booking->bookingSlot?->starts_at?->toDateTimeString(),
            'ends_at' => $booking->bookingSlot?->ends_at?->toDateTimeString(),
            'status' => $booking->status,
            'patient_note' => $booking->patient_note,
            'admin_note' => $booking->admin_note,
        ];
    }

    private function getRuleServiceIds(BookingAvailabilityRule $rule): Collection
    {
        $serviceIds = $rule->services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($serviceIds->isEmpty() && $rule->service_id) {
            return collect([(int) $rule->service_id]);
        }

        return $serviceIds;
    }

    private function bookingBelongsToAnyService(Booking $booking, Collection $serviceIds): bool
    {
        $bookingServiceIds = $booking->services
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($bookingServiceIds->isEmpty() && $booking->service_id) {
            $bookingServiceIds = collect([
                (int) $booking->service_id,
            ]);
        }

        return $bookingServiceIds
            ->intersect($serviceIds->map(fn ($id) => (int) $id))
            ->isNotEmpty();
    }
}