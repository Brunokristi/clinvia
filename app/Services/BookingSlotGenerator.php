<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\BookingSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingSlotGenerator
{
    public function generate(int $days = 60): int
    {
        return $this->generateForBranch(null, $days);
    }

    public function generateForBranch(?int $branchId, int $days = 60): int
    {
        $created = 0;
        $today = Carbon::today();

        $rulesQuery = BookingAvailabilityRule::query()
            ->with(['branch', 'services' => function ($query) {
                $query
                    ->where('is_bookable', true)
                    ->where('is_active', true);
            }])
            ->where('is_enabled', true);

        if ($branchId) {
            $rulesQuery->where('branch_id', $branchId);
        }

        $rulesQuery->get()->each(function (BookingAvailabilityRule $rule) use ($today, $days, &$created): void {
            $occurrences = $this->ruleDates($rule, $today, $days);

            foreach ($occurrences as $date) {
                if ($rule->slot_mode === 'single_service_many_clients') {
                    $created += $this->generateCapacityWindowSlot($rule, $date);

                    continue;
                }

                $created += $this->generateFreeBookableTimeSlots($rule, $date);
            }
        });

        return $created;
    }

    public function disableSlotsWithoutBookingsForRuleDate(BookingAvailabilityRule $rule, Carbon $date): int
    {
        $startsAt = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
        $endsAt = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);

        return BookingSlot::query()
            ->where('branch_id', $rule->branch_id)
            ->where('starts_at', '>=', $startsAt)
            ->where('ends_at', '<=', $endsAt)
            ->whereDoesntHave('bookings')
            ->update([
                'is_enabled' => false,
            ]);
    }

    public function disableSlotsWithoutBookingsForRuleFromDate(BookingAvailabilityRule $rule, Carbon $date): int
    {
        $startsAt = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);

        return BookingSlot::query()
            ->where('branch_id', $rule->branch_id)
            ->where('starts_at', '>=', $startsAt)
            ->whereDoesntHave('bookings')
            ->update([
                'is_enabled' => false,
            ]);
    }

    private function generateCapacityWindowSlot(BookingAvailabilityRule $rule, Carbon $date): int
    {
        $service = $rule->services->first();

        if (! $service) {
            return 0;
        }

        $startsAt = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
        $endsAt = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return 0;
        }

        if (! $this->slotCanExistForService(
            branchId: (int) $rule->branch_id,
            serviceId: (int) $service->id,
            startsAt: $startsAt,
            endsAt: $endsAt,
            bookingType: $service->booking_type ?? 'individual',
        )) {
            return 0;
        }

        $capacity = max(1, (int) ($rule->bookable_places ?? $service->capacity ?? 1));

        $slot = BookingSlot::firstOrCreate(
            [
                'branch_id' => $rule->branch_id,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ],
            [
                'capacity' => $capacity,
                'is_enabled' => true,
            ],
        );

        if (! $slot->wasRecentlyCreated) {
            $slot->update([
                'capacity' => $capacity,
                'is_enabled' => true,
            ]);

            return 0;
        }

        return 1;
    }

    private function generateFreeBookableTimeSlots(BookingAvailabilityRule $rule, Carbon $date): int
    {
        $created = 0;

        foreach ($rule->services as $service) {
            if (! $service->duration_minutes || $service->duration_minutes <= 0) {
                continue;
            }

            $cursor = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
            $ruleEnd = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);

            $stepMinutes = $service->duration_minutes
                + (int) ($service->buffer_before_minutes ?? 0)
                + (int) ($service->buffer_after_minutes ?? 0);

            if ($stepMinutes <= 0) {
                $stepMinutes = $service->duration_minutes;
            }

            while ($cursor->copy()->addMinutes($service->duration_minutes)->lessThanOrEqualTo($ruleEnd)) {
                $startsAt = $cursor->copy();
                $endsAt = $cursor->copy()->addMinutes($service->duration_minutes);

                if (! $this->slotCanExistForService(
                    branchId: (int) $rule->branch_id,
                    serviceId: (int) $service->id,
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    bookingType: $service->booking_type ?? 'individual',
                )) {
                    $cursor->addMinutes($stepMinutes);

                    continue;
                }

                $slot = BookingSlot::firstOrCreate(
                    [
                        'branch_id' => $rule->branch_id,
                        'service_id' => $service->id,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                    ],
                    [
                        'capacity' => max(1, (int) ($service->capacity ?? 1)),
                        'is_enabled' => true,
                    ],
                );

                if (! $slot->wasRecentlyCreated && ! $slot->is_enabled) {
                    $slot->update([
                        'is_enabled' => true,
                    ]);
                }

                if ($slot->wasRecentlyCreated) {
                    $created++;
                }

                $cursor->addMinutes($stepMinutes);
            }
        }

        return $created;
    }

    private function slotCanExistForService(
        int $branchId,
        int $serviceId,
        Carbon $startsAt,
        Carbon $endsAt,
        string $bookingType,
    ): bool {
        $overlappingBookings = Booking::query()
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->whereHas('bookingSlot', function ($query) use ($startsAt, $endsAt) {
                $query
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            })
            ->get();

        if ($bookingType !== 'group') {
            return $overlappingBookings->isEmpty();
        }

        return $overlappingBookings
            ->filter(function (Booking $booking) use ($serviceId) {
                return (int) $booking->service_id !== $serviceId;
            })
            ->isEmpty();
    }

    private function ruleDates(BookingAvailabilityRule $rule, Carbon $today, int $days): Collection
    {
        $endDate = $today->copy()->addDays($days)->endOfDay();
        $disabledDayService = app(DisabledDayService::class);

        $excludedDates = collect($rule->excluded_dates ?? [])
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $repeatEndsOn = $rule->repeat_ends_on
            ? Carbon::parse($rule->repeat_ends_on)->endOfDay()
            : null;

        if ($repeatEndsOn && $repeatEndsOn->lt($today)) {
            return collect();
        }

        $effectiveEndDate = $repeatEndsOn && $repeatEndsOn->lt($endDate)
            ? $repeatEndsOn
            : $endDate;

        if ($rule->date) {
            $baseDate = Carbon::parse($rule->date)->startOfDay();

            if (! $rule->repeats) {
                $baseDateString = $baseDate->toDateString();

                return $baseDate->betweenIncluded($today, $effectiveEndDate)
                    && ! in_array($baseDateString, $excludedDates, true)
                    && ! $disabledDayService->isDisabled($rule->branch, $baseDate)
                        ? collect([$baseDate])
                        : collect();
            }

            $dates = collect();
            $current = $baseDate->copy();

            while ($current->lessThan($today)) {
                $current = $this->nextRepeatedDate($current, $rule);
            }

            while ($current->lessThanOrEqualTo($effectiveEndDate)) {
                $currentDateString = $current->toDateString();

                if (! in_array($currentDateString, $excludedDates, true)) {
                    if (! $disabledDayService->isDisabled($rule->branch, $current)) {
                        $dates->push($current->copy());
                    }
                }

                $current = $this->nextRepeatedDate($current, $rule);
            }

            return $dates;
        }

        return collect(range(0, $days - 1))
            ->map(fn (int $offset) => $today->copy()->addDays($offset))
            ->filter(function (Carbon $date) use ($rule, $excludedDates, $effectiveEndDate) {
                return $date->lessThanOrEqualTo($effectiveEndDate)
                    && (int) $date->dayOfWeekIso === (int) $rule->day_of_week
                    && ! in_array($date->toDateString(), $excludedDates, true)
                    && ! app(DisabledDayService::class)->isDisabled($rule->branch, $date);
            })
            ->values();
    }

    private function nextRepeatedDate(Carbon $date, BookingAvailabilityRule $rule): Carbon
    {
        $repeatEvery = max(1, (int) ($rule->repeat_every ?? 1));

        return match ($rule->repeat_unit) {
            'days' => $date->copy()->addDays($repeatEvery),
            'months' => $date->copy()->addMonths($repeatEvery),
            default => $date->copy()->addWeeks($repeatEvery),
        };
    }
}