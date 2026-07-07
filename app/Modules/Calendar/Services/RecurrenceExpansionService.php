<?php

namespace App\Modules\Calendar\Services;

use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Modules\Calendar\Models\Event;
use App\Services\OpeningHoursService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class RecurrenceExpansionService
{
    public function __construct(
        private readonly EventOccurrenceService $eventOccurrenceService,
        private readonly OpeningHoursService $openingHoursService,
    ) {
    }

    public function forBranch(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd, ?array $types = null, bool $includeCancelled = false): Collection
    {
        $events = Event::query()
            ->with(['services', 'bookingDetail', 'availabilityRuleDetail', 'groupDetail', 'participants'])
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->when($types !== null && $types !== [], fn ($query) => $query->whereIn('type', $types))
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->where(function ($subQuery) use ($rangeStart, $rangeEnd) {
                        $subQuery
                            ->where('starts_at', '<', $rangeEnd)
                            ->where('ends_at', '>', $rangeStart);
                    })
                    ->orWhere(function ($subQuery) use ($rangeEnd) {
                        $subQuery
                            ->where('is_recurring', true)
                            ->whereNull('recurrence_parent_id')
                            ->where('starts_at', '<', $rangeEnd);
                    })
                    ->orWhere(function ($subQuery) use ($rangeStart, $rangeEnd) {
                        $subQuery
                            ->whereNotNull('recurrence_parent_id')
                            ->whereNotNull('recurrence_original_starts_at')
                            ->where('recurrence_original_starts_at', '<', $rangeEnd)
                            ->where('recurrence_original_starts_at', '>=', $rangeStart);
                    });
            })
            ->orderBy('starts_at')
            ->get();

        return $this->forEvents($events, $rangeStart, $rangeEnd, $includeCancelled, $branch);
    }

    public function forEvents(
        Collection $events,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        bool $includeCancelled = false,
        ?Branch $branch = null,
    ): Collection {
        if ($events->isEmpty()) {
            return collect();
        }

        $disabledDateContext = $this->buildDisabledDateContext($branch, $rangeStart, $rangeEnd);

        return $this->expandCollection($events, $rangeStart, $rangeEnd, $includeCancelled, $branch, $disabledDateContext);
    }

    public function expandCollection(
        Collection $events,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        bool $includeCancelled = false,
        ?Branch $branch = null,
        array $disabledDateContext = [],
    ): Collection
    {
        $roots = $events
            ->filter(fn (Event $event) => $event->recurrence_parent_id === null)
            ->values();

        $overrides = $events
            ->filter(fn (Event $event) => $event->recurrence_parent_id !== null)
            ->groupBy('recurrence_parent_id');

        return $roots
            ->flatMap(fn (Event $root) => $this->expandRoot($root, $overrides->get($root->id, collect()), $rangeStart, $rangeEnd, $includeCancelled, $branch, $disabledDateContext))
            ->values();
    }

    public function expandRoot(
        Event $root,
        Collection $overrides,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        bool $includeCancelled = false,
        ?Branch $branch = null,
        array $disabledDateContext = [],
    ): Collection
    {
        if (! $root->is_recurring || empty($root->recurrence_rule)) {
            if (! $this->overlapsRange($root, $rangeStart, $rangeEnd)) {
                return collect();
            }

            if (! $includeCancelled && $root->status === 'cancelled') {
                return collect();
            }

            if ($branch && ! $this->occurrenceWindowIsAllowed($branch, $root->starts_at, $root->ends_at, $disabledDateContext)) {
                return collect();
            }

            return collect([
                $this->makeOccurrencePayload($root, $root->starts_at?->copy(), $root->ends_at?->copy(), false, false, $root->status === 'cancelled'),
            ]);
        }

        $occurrences = $this->eventOccurrenceService->getOccurrenceDates($root, $rangeStart, $rangeEnd);

        $overrideMap = $overrides
            ->filter(fn (Event $override) => $override->recurrence_original_starts_at)
            ->keyBy(fn (Event $override) => $override->recurrence_original_starts_at->copy()->utc()->format('Y-m-d\TH:i:s'));

        $occurrenceStartKeys = $occurrences
            ->map(function (Carbon $occurrenceDate) use ($root) {
                $occurrenceStartsAt = $this->combineOccurrenceDate($root->starts_at, $occurrenceDate);

                return $occurrenceStartsAt?->copy()->utc()->format('Y-m-d\TH:i:s');
            })
            ->filter()
            ->values();

        $occurrenceStartKeyLookup = array_fill_keys($occurrenceStartKeys->all(), true);

        $expandedOccurrences = $occurrences
            ->map(function (Carbon $occurrenceDate) use ($root, $overrideMap, $includeCancelled, $branch, $disabledDateContext, $rangeStart, $rangeEnd) {
                $occurrenceStartsAt = $this->combineOccurrenceDate($root->starts_at, $occurrenceDate);
                $occurrenceEndsAt = $this->combineOccurrenceDate($root->ends_at, $occurrenceDate);

                if (! $occurrenceStartsAt || ! $occurrenceEndsAt) {
                    return null;
                }

                $override = $overrideMap->get($occurrenceStartsAt->copy()->utc()->format('Y-m-d\TH:i:s'));

                if ($override) {
                    if (! $this->windowOverlapsRange($override->starts_at, $override->ends_at, $rangeStart, $rangeEnd)) {
                        return null;
                    }

                    if (! $includeCancelled && $override->status === 'cancelled') {
                        return null;
                    }

                    if ($branch && ! $this->occurrenceWindowIsAllowed($branch, $override->starts_at, $override->ends_at, $disabledDateContext)) {
                        return null;
                    }

                    return $this->makeOccurrencePayload(
                        $override,
                        $override->starts_at?->copy(),
                        $override->ends_at?->copy(),
                        true,
                        true,
                        $override->status === 'cancelled',
                        $root,
                        $occurrenceStartsAt,
                        $occurrenceEndsAt,
                    );
                }

                if (! $this->windowOverlapsRange($occurrenceStartsAt, $occurrenceEndsAt, $rangeStart, $rangeEnd)) {
                    return null;
                }

                if (! $includeCancelled && $root->status === 'cancelled') {
                    return null;
                }

                if ($branch && ! $this->occurrenceWindowIsAllowed($branch, $occurrenceStartsAt, $occurrenceEndsAt, $disabledDateContext)) {
                    return null;
                }

                return $this->makeOccurrencePayload(
                    $root,
                    $occurrenceStartsAt,
                    $occurrenceEndsAt,
                    true,
                    false,
                    $root->status === 'cancelled',
                    $root,
                    $occurrenceStartsAt,
                    $occurrenceEndsAt,
                );
            })
            ->filter()
            ->values();

        $detachedOverridesInRange = $overrideMap
            ->filter(fn (Event $override, string $key) => ! isset($occurrenceStartKeyLookup[$key]))
            ->map(function (Event $override) use ($includeCancelled, $branch, $disabledDateContext, $root, $rangeStart, $rangeEnd) {
                if (! $this->windowOverlapsRange($override->starts_at, $override->ends_at, $rangeStart, $rangeEnd)) {
                    return null;
                }

                if (! $includeCancelled && $override->status === 'cancelled') {
                    return null;
                }

                if ($branch && ! $this->occurrenceWindowIsAllowed($branch, $override->starts_at, $override->ends_at, $disabledDateContext)) {
                    return null;
                }

                return $this->makeOccurrencePayload(
                    $override,
                    $override->starts_at?->copy(),
                    $override->ends_at?->copy(),
                    true,
                    true,
                    $override->status === 'cancelled',
                    $root,
                    $override->recurrence_original_starts_at?->copy(),
                    $override->recurrence_original_ends_at?->copy(),
                );
            })
            ->filter()
            ->values();

        return $expandedOccurrences
            ->merge($detachedOverridesInRange)
            ->sortBy(fn (array $occurrence) => sprintf(
                '%s|%s',
                $occurrence['occurrence_starts_at']?->copy()->utc()->format('Y-m-d\TH:i:s') ?? '0000-00-00T00:00:00',
                (string) ($occurrence['occurrence_id'] ?? ''),
            ))
            ->values();
    }

    private function makeOccurrencePayload(
        Event $event,
        ?Carbon $startsAt,
        ?Carbon $endsAt,
        bool $isOccurrence,
        bool $isOverride,
        bool $isCancelled,
        ?Event $rootEvent = null,
        ?Carbon $occurrenceOriginalStartsAt = null,
        ?Carbon $occurrenceOriginalEndsAt = null,
    ): array {
        $rootEvent ??= $event->recurrenceParent ?? $event;
        $occurrenceOriginalStartsAt ??= $event->recurrence_original_starts_at ?? $startsAt;
        $occurrenceOriginalEndsAt ??= $event->recurrence_original_ends_at ?? $endsAt;
        $logicalRootEventId = (int) ($rootEvent->root_event_id ?? $rootEvent->id);
        $displayKey = $occurrenceOriginalStartsAt
            ? sprintf('%d:%s', $logicalRootEventId, $occurrenceOriginalStartsAt->copy()->utc()->format('Y-m-d\TH:i:s'))
            : sprintf('single:%d', (int) $event->id);

        return [
            'event' => $event,
            'root_event' => $rootEvent,
            'event_id' => $event->id,
            'root_event_id' => $logicalRootEventId,
            'recurring_master_id' => (int) $rootEvent->id,
            'occurrence_id' => sprintf('%d:%s', $logicalRootEventId, $occurrenceOriginalStartsAt?->copy()->utc()->format('Y-m-d\TH:i:s') ?? 'unknown'),
            'occurrence_starts_at' => $startsAt,
            'occurrence_ends_at' => $endsAt,
            'occurrence_original_starts_at' => $occurrenceOriginalStartsAt,
            'occurrence_original_ends_at' => $occurrenceOriginalEndsAt,
            'display_key' => $displayKey,
            'is_recurring' => (bool) ($rootEvent->is_recurring || $isOccurrence),
            'is_occurrence' => $isOccurrence,
            'is_override' => $isOverride,
            'is_cancelled' => $isCancelled,
        ];
    }

    private function combineOccurrenceDate(?Carbon $source, Carbon $occurrenceDate): ?Carbon
    {
        if (! $source) {
            return null;
        }

        return Carbon::parse($occurrenceDate->toDateString() . ' ' . $source->format('H:i:s'), $source->getTimezone())
            ->setTimezone($source->getTimezone());
    }

    private function overlapsRange(Event $event, Carbon $rangeStart, Carbon $rangeEnd): bool
    {
        if (! $event->starts_at || ! $event->ends_at) {
            return false;
        }

        return $event->starts_at->lt($rangeEnd) && $event->ends_at->gt($rangeStart);
    }

    private function windowOverlapsRange(?Carbon $startsAt, ?Carbon $endsAt, Carbon $rangeStart, Carbon $rangeEnd): bool
    {
        if (! $startsAt || ! $endsAt) {
            return false;
        }

        return $startsAt->lt($rangeEnd) && $endsAt->gt($rangeStart);
    }

    private function occurrenceWindowIsAllowed(Branch $branch, ?Carbon $startsAt, ?Carbon $endsAt, array $disabledDateContext): bool
    {
        if (! $startsAt || ! $endsAt) {
            return false;
        }

        if ($this->isDisabledDate($startsAt->toDateString(), $disabledDateContext)) {
            return false;
        }

        return $this->openingHoursService->isWithinOpeningHours($branch, $startsAt, $endsAt);
    }

    private function buildDisabledDateContext(?Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $manualClosedDates = [];
        $holidayOpenDates = [];

        if ($branch && Schema::hasTable('branch_disabled_days')) {
            $rows = BranchDisabledDay::query()
                ->where('branch_id', $branch->id)
                ->whereBetween('date', [
                    $rangeStart->copy()->startOfDay(),
                    $rangeEnd->copy()->endOfDay(),
                ])
                ->get(['date', 'type']);

            foreach ($rows as $row) {
                $date = $row->date?->toDateString();

                if (! $date) {
                    continue;
                }

                if ($row->type === 'holiday_open') {
                    $holidayOpenDates[$date] = true;

                    continue;
                }

                $manualClosedDates[$date] = true;
            }
        }

        return [
            'manual_closed_dates' => $manualClosedDates,
            'holiday_open_dates' => $holidayOpenDates,
        ];
    }

    private function isDisabledDate(string $date, array $disabledDateContext): bool
    {
        if (($disabledDateContext['manual_closed_dates'][$date] ?? false) === true) {
            return true;
        }

        if (($disabledDateContext['holiday_open_dates'][$date] ?? false) === true) {
            return false;
        }

        return $this->isHolidayDate($date);
    }

    private function isHolidayDate(string $date): bool
    {
        $year = (int) substr($date, 0, 4);

        return array_key_exists($date, $this->stateHolidayMapForYear($year));
    }

    private function stateHolidayMapForYear(int $year): array
    {
        $map = collect($this->holidayDefinitionsForYear($year))
            ->filter(fn (array $holiday): bool => (bool) ($holiday['is_state_holiday'] ?? false))
            ->mapWithKeys(fn (array $holiday): array => [
                $holiday['date'] => $holiday['title'],
            ])
            ->all();

        ksort($map);

        return $map;
    }

    private function holidayDefinitionsForYear(int $year): array
    {
        $easterSunday = Carbon::createFromTimestamp((int) easter_date($year))->setTimezone(config('app.timezone'));

        return [
            ['date' => sprintf('%d-01-01', $year), 'title' => 'Den vzniku Slovenskej republiky', 'is_state_holiday' => true],
            ['date' => sprintf('%d-01-06', $year), 'title' => 'Zjavenie Pana', 'is_state_holiday' => false],
            ['date' => $easterSunday->copy()->subDays(2)->toDateString(), 'title' => 'Velky piatok', 'is_state_holiday' => false],
            ['date' => $easterSunday->copy()->addDay()->toDateString(), 'title' => 'Velkonocny pondelok', 'is_state_holiday' => false],
            ['date' => sprintf('%d-05-01', $year), 'title' => 'Sviatok prace', 'is_state_holiday' => false],
            ['date' => sprintf('%d-05-08', $year), 'title' => 'Den vitazstva nad fasizmom', 'is_state_holiday' => false],
            ['date' => sprintf('%d-07-05', $year), 'title' => 'Sviatok svateho Cyrila a Metoda', 'is_state_holiday' => true],
            ['date' => sprintf('%d-08-29', $year), 'title' => 'Vyrocie SNP', 'is_state_holiday' => true],
            ['date' => sprintf('%d-09-01', $year), 'title' => 'Den Ustavy Slovenskej republiky', 'is_state_holiday' => true],
            ['date' => sprintf('%d-10-28', $year), 'title' => 'Den vzniku samostatneho cesko-slovenskeho statu (nie je dnom pracovneho pokoja)', 'is_state_holiday' => true],
            ['date' => sprintf('%d-11-17', $year), 'title' => 'Den boja za slobodu a demokraciu', 'is_state_holiday' => true],
            ['date' => sprintf('%d-09-15', $year), 'title' => 'Sedembolestna Panna Maria', 'is_state_holiday' => false],
            ['date' => sprintf('%d-11-01', $year), 'title' => 'Sviatok vsetkych svatych', 'is_state_holiday' => false],
            ['date' => sprintf('%d-12-24', $year), 'title' => 'Stedry den', 'is_state_holiday' => false],
            ['date' => sprintf('%d-12-25', $year), 'title' => 'Prvy sviatok vianocny', 'is_state_holiday' => false],
            ['date' => sprintf('%d-12-26', $year), 'title' => 'Druhy sviatok vianocny', 'is_state_holiday' => false],
        ];
    }
}