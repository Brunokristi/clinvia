<?php

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Enums\RecurrenceScope;
use App\Modules\Calendar\Models\Event;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EventOccurrenceService
{
    public function __construct(
        private readonly RecurrenceService $recurrenceService,
    ) {
    }

    public function resolveScope(string|RecurrenceScope|null $scope): RecurrenceScope
    {
        if ($scope instanceof RecurrenceScope) {
            return $scope;
        }

        return RecurrenceScope::tryFrom((string) $scope) ?? RecurrenceScope::This;
    }

    public function getOccurrenceDates(Event $event, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        if (! $event->is_recurring || empty($event->recurrence_rule) || ! $event->starts_at) {
            return collect();
        }

        return $this->recurrenceService->getOccurrenceDates(
            seriesStart: $event->starts_at->copy(),
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
            recurrence: $event->recurrence_rule,
            excludedDates: $this->excludedDates($event),
        );
    }

    public function addExceptionDate(Event $event, Carbon $date): void
    {
        $metadata = $event->metadata ?? [];
        $existing = collect($metadata['recurrence_excluded_dates'] ?? [])
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->push($date->toDateString())
            ->unique()
            ->values()
            ->all();

        $metadata['recurrence_excluded_dates'] = $existing;

        $event->metadata = $metadata;
        $event->save();
    }

    public function excludedDates(Event $event): array
    {
        return collect(data_get($event->metadata, 'recurrence_excluded_dates', []))
            ->filter()
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->unique()
            ->values()
            ->all();
    }
}
