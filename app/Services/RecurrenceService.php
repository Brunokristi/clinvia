<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurrenceService
{
    public function normalize(array $recurrence): array
    {
        $requestedFrequency = $recurrence['frequency'] ?? 'weekly';
        $frequency = in_array($requestedFrequency, ['daily', 'weekly', 'monthly', 'yearly'], true)
            ? $requestedFrequency
            : 'weekly';

        $interval = max(1, (int) ($recurrence['interval'] ?? 1));

        $weekdays = collect($recurrence['weekdays'] ?? [])
            ->map(fn ($weekday) => strtoupper((string) $weekday))
            ->filter(fn (string $weekday) => in_array($weekday, ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], true))
            ->unique()
            ->values()
            ->all();

        $ends = $recurrence['ends'] ?? [];
        $requestedEndsType = $ends['type'] ?? 'never';
        $endsType = in_array($requestedEndsType, ['never', 'on', 'after'], true)
            ? $requestedEndsType
            : 'never';

        return [
            'frequency' => $frequency,
            'interval' => $interval,
            'weekdays' => $weekdays,
            'ends' => [
                'type' => $endsType,
                'count' => $endsType === 'after' ? max(1, (int) ($ends['count'] ?? 1)) : null,
                'until' => $endsType === 'on' && ! empty($ends['until'])
                    ? Carbon::parse($ends['until'])->toDateString()
                    : null,
            ],
        ];
    }

    public function isRecurring(?array $recurrence): bool
    {
        return filled($recurrence) && ($recurrence['frequency'] ?? null) !== null;
    }

    public function getOccurrenceDates(
        Carbon $seriesStart,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        array $recurrence,
        array $excludedDates = [],
    ): Collection {
        $normalized = $this->normalize($recurrence);
        $excludedDates = collect($excludedDates)
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->all();

        $firstCandidate = $seriesStart->copy()->startOfDay();
        $searchStart = $rangeStart->copy()->startOfDay();
        $searchEnd = $rangeEnd->copy()->endOfDay();

        if ($normalized['ends']['type'] === 'on' && $normalized['ends']['until']) {
            $searchEnd = min($searchEnd, Carbon::parse($normalized['ends']['until'])->endOfDay());
        }

        $occurrences = collect();
        $count = 0;

        $cursor = $firstCandidate->copy();
        while ($cursor->lte($searchEnd)) {
            if ($this->matchesOccurrence($seriesStart, $cursor, $normalized) && ! in_array($cursor->toDateString(), $excludedDates, true)) {
                $count++;

                if ($normalized['ends']['type'] === 'after' && $normalized['ends']['count'] && $count > $normalized['ends']['count']) {
                    break;
                }

                if ($cursor->betweenIncluded($searchStart, $searchEnd)) {
                    $occurrences->push($cursor->copy());
                }
            }

            $cursor->addDay();
        }

        return $occurrences->values();
    }

    private function matchesOccurrence(Carbon $seriesStart, Carbon $candidate, array $recurrence): bool
    {
        if ($candidate->lt($seriesStart->copy()->startOfDay())) {
            return false;
        }

        $interval = max(1, (int) ($recurrence['interval'] ?? 1));
        $seriesDay = $seriesStart->copy()->startOfDay();

        return match ($recurrence['frequency'] ?? 'weekly') {
            'daily' => $seriesDay->diffInDays($candidate) % $interval === 0,
            'monthly' => $seriesDay->diffInMonths($candidate) % $interval === 0
                && (int) $seriesDay->day === (int) $candidate->day,
            'yearly' => $seriesDay->diffInYears($candidate) % $interval === 0
                && (int) $seriesDay->format('m') === (int) $candidate->format('m')
                && (int) $seriesDay->day === (int) $candidate->day,
            default => $this->matchesWeeklyOccurrence($seriesDay, $candidate, $recurrence, $interval),
        };
    }

    private function matchesWeeklyOccurrence(Carbon $seriesDay, Carbon $candidate, array $recurrence, int $interval): bool
    {
        $weekdays = $recurrence['weekdays'] ?? [];

        if ($weekdays === []) {
            $weekdays = [$this->weekdayCode($seriesDay)];
        }

        if (! in_array($this->weekdayCode($candidate), $weekdays, true)) {
            return false;
        }

        $seriesWeekStart = $seriesDay->copy()->startOfWeek(Carbon::MONDAY);
        $candidateWeekStart = $candidate->copy()->startOfWeek(Carbon::MONDAY);

        return $seriesWeekStart->diffInWeeks($candidateWeekStart) % $interval === 0;
    }

    private function weekdayCode(Carbon $date): string
    {
        return match ((int) $date->dayOfWeekIso) {
            1 => 'MO',
            2 => 'TU',
            3 => 'WE',
            4 => 'TH',
            5 => 'FR',
            6 => 'SA',
            default => 'SU',
        };
    }
}
