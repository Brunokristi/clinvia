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
                            ->where('starts_at', '<=', $rangeEnd)
                            ->where('ends_at', '>=', $rangeStart);
                    })
                    ->orWhere(function ($subQuery) use ($rangeEnd) {
                        $subQuery
                            ->where('is_recurring', true)
                            ->whereNull('recurrence_parent_id')
                            ->where('starts_at', '<=', $rangeEnd);
                    });
            })
            ->orderBy('starts_at')
            ->get();

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

        return $occurrences
            ->map(function (Carbon $occurrenceDate) use ($root, $overrideMap, $includeCancelled, $branch, $disabledDateContext) {
                $occurrenceStartsAt = $this->combineOccurrenceDate($root->starts_at, $occurrenceDate);
                $occurrenceEndsAt = $this->combineOccurrenceDate($root->ends_at, $occurrenceDate);

                if (! $occurrenceStartsAt || ! $occurrenceEndsAt) {
                    return null;
                }

                $override = $overrideMap->get($occurrenceStartsAt->copy()->utc()->format('Y-m-d\TH:i:s'));

                if ($override) {
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

        return [
            'event' => $event,
            'root_event' => $rootEvent,
            'event_id' => $event->id,
            'root_event_id' => $rootEvent->id,
            'occurrence_id' => sprintf('%d:%s', $rootEvent->id, $occurrenceOriginalStartsAt?->copy()->utc()->format('Y-m-d\TH:i:s') ?? 'unknown'),
            'occurrence_starts_at' => $startsAt,
            'occurrence_ends_at' => $endsAt,
            'occurrence_original_starts_at' => $occurrenceOriginalStartsAt,
            'occurrence_original_ends_at' => $occurrenceOriginalEndsAt,
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

        return $event->starts_at->lte($rangeEnd) && $event->ends_at->gte($rangeStart);
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

    private function buildDisabledDateContext(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $manualClosedDates = [];
        $holidayOpenDates = [];

        if (Schema::hasTable('branch_disabled_days')) {
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

        return array_key_exists($date, $this->holidayMapForYear($year));
    }

    private function holidayMapForYear(int $year): array
    {
        $fixed = [
            sprintf('%d-01-01', $year) => 'Den vzniku Slovenskej republiky',
            sprintf('%d-01-06', $year) => 'Zjavenie Pana',
            sprintf('%d-05-01', $year) => 'Sviatok prace',
            sprintf('%d-05-08', $year) => 'Den vitazstva nad fasizmom',
            sprintf('%d-07-05', $year) => 'Sviatok svateho Cyrila a Metoda',
            sprintf('%d-08-29', $year) => 'Vyrocie SNP',
            sprintf('%d-09-01', $year) => 'Den Ustavy Slovenskej republiky',
            sprintf('%d-09-15', $year) => 'Sedembolestna Panna Maria',
            sprintf('%d-11-01', $year) => 'Sviatok vsetkych svatych',
            sprintf('%d-11-17', $year) => 'Den boja za slobodu a demokraciu',
            sprintf('%d-12-24', $year) => 'Stedry den',
            sprintf('%d-12-25', $year) => 'Prvy sviatok vianocny',
            sprintf('%d-12-26', $year) => 'Druhy sviatok vianocny',
        ];

        $easterSunday = Carbon::createFromTimestamp((int) easter_date($year))->setTimezone(config('app.timezone'));

        $fixed[$easterSunday->copy()->subDays(2)->toDateString()] = 'Velky piatok';
        $fixed[$easterSunday->copy()->addDay()->toDateString()] = 'Velkonocny pondelok';

        ksort($fixed);

        return $fixed;
    }
}