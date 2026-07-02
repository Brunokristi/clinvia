<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\OpeningHour;
use App\Models\OpeningHourInterval;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OpeningHoursService
{
    public function isWithinOpeningHours(Branch $branch, Carbon|string $startsAt, Carbon|string $endsAt): bool
    {
        $branch->loadMissing('openingHours.intervals');

        if ($branch->openingHours->isEmpty()) {
            return true;
        }

        $start = $this->normalizeDateTime($startsAt);
        $end = $this->normalizeDateTime($endsAt);

        if (! $start || ! $end) {
            return false;
        }

        if ($start->toDateString() !== $end->toDateString()) {
            return false;
        }

        $openingHour = $this->getOpeningHourForDate($branch, $start);

        if (! $openingHour) {
            return false;
        }

        $intervals = $this->getOpeningIntervals($openingHour);

        if ($intervals->isEmpty()) {
            return false;
        }

        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        return $intervals->contains(function (OpeningHourInterval $interval) use ($startTime, $endTime): bool {
            $intervalStart = $this->normalizeTimeValue($interval->opens_at ?? $interval->starts_at ?? null);
            $intervalEnd = $this->normalizeTimeValue($interval->closes_at ?? $interval->ends_at ?? null);

            if (! $intervalStart || ! $intervalEnd) {
                return false;
            }

            return $startTime >= $intervalStart && $endTime <= $intervalEnd;
        });
    }

    public function getOpeningHourForDate(Branch $branch, Carbon|string $date): ?OpeningHour
    {
        $branch->loadMissing('openingHours.intervals');

        $targetDate = $this->normalizeDateTime($date);

        if (! $targetDate) {
            return null;
        }

        $databaseDay = $targetDate->dayOfWeekIso;

        return $branch->openingHours->first(function (OpeningHour $openingHour) use ($databaseDay): bool {
            return (int) $openingHour->day_of_week === (int) $databaseDay;
        });
    }

    public function getOpeningIntervals(OpeningHour $openingHour): Collection
    {
        return $openingHour->intervals ?? collect();
    }

    private function normalizeDateTime(Carbon|string $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $stringValue)) {
            return $stringValue . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $stringValue)) {
            return $stringValue;
        }

        try {
            return Carbon::parse($stringValue)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
