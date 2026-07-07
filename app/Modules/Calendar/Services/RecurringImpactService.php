<?php

namespace App\Modules\Calendar\Services;

use App\Models\Branch;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RecurringImpactService
{
    private const PREVIEW_COUNT_LIMIT = 50;

    public function __construct(
        private readonly RecurrenceExpansionService $recurrenceExpansionService,
        private readonly EventOccurrenceService $eventOccurrenceService,
    ) {
    }

    public function preview(
        Branch $branch,
        array $selectedOccurrence,
        string $action,
        string $scope,
        array $changes = [],
    ): array {
        $normalizedScope = $this->normalizeScope($scope);
        $event = $this->resolveEvent($branch, $selectedOccurrence);
        $eventType = $event->type->value;

        $selectedStartsAt = $this->resolveSelectedStartsAt($event, $selectedOccurrence);
        $selectedOriginalStartsAt = $this->resolveSelectedOriginalStartsAt($selectedOccurrence, $selectedStartsAt);
        $logicalRootEventId = (int) ($selectedOccurrence['root_event_id'] ?? $event->root_event_id ?? $event->id);

        $selectedDisplayKey = $this->resolveDisplayKey($event, $selectedOccurrence, $logicalRootEventId, $selectedOriginalStartsAt);

        $masters = $this->logicalMasters($event, $logicalRootEventId);
        $cutoff = now()->startOfMinute();

        [$impactWindowFrom, $impactWindowTo, $isInfiniteSeries] = $this->resolveImpactWindow(
            masters: $masters,
            selectedStartsAt: $selectedStartsAt,
            scope: $normalizedScope,
            eventType: $eventType,
            cutoff: $cutoff,
        );

        $visibleOccurrences = $this->expandedLogicalOccurrences(
            branch: $branch,
            eventType: $event->type,
            rootEventId: $logicalRootEventId,
            from: $impactWindowFrom,
            to: $impactWindowTo,
        );

        $pastNotModified = $this->pastOccurrencesNotModifiedCount(
            branch: $branch,
            eventType: $event->type,
            rootEventId: $logicalRootEventId,
            masters: $masters,
            scope: $normalizedScope,
            cutoff: $cutoff,
        );

        $affectedOccurrences = $this->affectedOccurrencesByScope(
            allOccurrences: $visibleOccurrences,
            scope: $normalizedScope,
            selectedStartsAt: $selectedStartsAt,
            selectedDisplayKey: $selectedDisplayKey,
            selectedOccurrence: $selectedOccurrence,
            event: $event,
            logicalRootEventId: $logicalRootEventId,
            cutoff: $cutoff,
            eventType: $eventType,
        );

        $isAffectedCountCapped = $affectedOccurrences->count() > self::PREVIEW_COUNT_LIMIT;

        if ($isAffectedCountCapped) {
            $affectedOccurrences = $affectedOccurrences
                ->take(self::PREVIEW_COUNT_LIMIT + 1)
                ->values();
        }

        $affectedOccurrencesPayload = $affectedOccurrences
            ->map(fn (array $occurrence): array => Arr::except($occurrence, ['event']))
            ->values();

        $affectedOccurrenceCount = $isAffectedCountCapped
            ? self::PREVIEW_COUNT_LIMIT + 1
            : $affectedOccurrencesPayload->count();
        $affectedExceptionCount = $affectedOccurrencesPayload->where('is_exception', true)->count();
        $affectedMasterCount = $masters->count();

        $affectedBookingCount = $eventType === EventType::Booking->value
            ? $affectedOccurrenceCount
            : 0;

        $affectedConflictingBookingCount = $eventType === EventType::AvailabilityRule->value
            ? $this->countConflictingBookings($branch, $affectedOccurrences, $impactWindowFrom, $impactWindowTo)
            : 0;

        $affectedParticipantCount = $eventType === EventType::GroupEvent->value
            ? $this->countAffectedParticipants($affectedOccurrences)
            : 0;

        $message = $this->buildMessage(
            scope: $normalizedScope,
            eventType: $eventType,
            isInfiniteSeries: $isInfiniteSeries,
            affectedOccurrenceCount: $affectedOccurrenceCount,
            pastNotModifiedCount: $pastNotModified,
            conflictingBookings: $affectedConflictingBookingCount,
            participantCount: $affectedParticipantCount,
        );

        return [
            'action' => $action,
            'scope' => $normalizedScope,
            'event_type' => $eventType,
            'root_event_id' => $logicalRootEventId,
            'selected_display_key' => $selectedDisplayKey,
            'selected_original_start_at' => $selectedOriginalStartsAt?->toIso8601String(),
            'selected_starts_at' => $selectedStartsAt?->toIso8601String(),
            'impact_window_from' => $impactWindowFrom?->toIso8601String(),
            'impact_window_to' => $impactWindowTo?->toIso8601String(),
            'is_infinite_series' => $isInfiniteSeries,
            'affected_occurrence_count' => $affectedOccurrenceCount,
            'affected_occurrences' => $affectedOccurrencesPayload->all(),
            'affected_master_count' => $affectedMasterCount,
            'affected_exception_count' => $affectedExceptionCount,
            'affected_booking_count' => $affectedBookingCount,
            'affected_conflicting_booking_count' => $affectedConflictingBookingCount,
            'affected_participant_count' => $affectedParticipantCount,
            'past_occurrence_count_not_modified' => $pastNotModified,
            'message' => $message,
            'changes' => $changes,
        ];
    }

    private function normalizeScope(string $scope): string
    {
        return match ($scope) {
            'occurrence', 'this' => 'this',
            'from_date', 'this_and_following' => 'this_and_following',
            'series', 'all' => 'all',
            default => 'this',
        };
    }

    private function resolveEvent(Branch $branch, array $selectedOccurrence): Event
    {
        $eventId = (int) ($selectedOccurrence['event_id'] ?? 0);

        $event = Event::query()
            ->with(['participants'])
            ->where('branch_id', $branch->id)
            ->whereKey($eventId)
            ->first();

        if (! $event) {
            throw ValidationException::withMessages([
                'event_id' => 'Selected occurrence event was not found for impact preview.',
            ]);
        }

        return $event;
    }

    private function resolveSelectedStartsAt(Event $event, array $selectedOccurrence): Carbon
    {
        if (filled($selectedOccurrence['occurrence_starts_at'] ?? null)) {
            return Carbon::parse($selectedOccurrence['occurrence_starts_at']);
        }

        if (filled($selectedOccurrence['starts_at'] ?? null)) {
            return Carbon::parse($selectedOccurrence['starts_at']);
        }

        return $event->starts_at?->copy() ?? now();
    }

    private function resolveSelectedOriginalStartsAt(array $selectedOccurrence, Carbon $selectedStartsAt): Carbon
    {
        if (filled($selectedOccurrence['occurrence_original_starts_at'] ?? null)) {
            return Carbon::parse($selectedOccurrence['occurrence_original_starts_at']);
        }

        if (filled($selectedOccurrence['original_start_at'] ?? null)) {
            return Carbon::parse($selectedOccurrence['original_start_at']);
        }

        return $selectedStartsAt;
    }

    private function resolveDisplayKey(Event $event, array $selectedOccurrence, int $rootEventId, Carbon $selectedOriginalStartsAt): string
    {
        if (filled($selectedOccurrence['display_key'] ?? null)) {
            return (string) $selectedOccurrence['display_key'];
        }

        if ($event->is_recurring || $event->recurrence_parent_id !== null) {
            return sprintf(
                '%d:%s',
                $rootEventId,
                $selectedOriginalStartsAt->copy()->setTimezone($event->timezone ?? config('app.timezone'))->format('Y-m-d H:i')
            );
        }

        return sprintf('single:%d', (int) $event->id);
    }

    private function logicalMasters(Event $selectedEvent, int $rootEventId): Collection
    {
        return Event::query()
            ->where('branch_id', $selectedEvent->branch_id)
            ->where('type', $selectedEvent->type)
            ->whereNull('recurrence_parent_id')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($rootEventId): void {
                $query->where('root_event_id', $rootEventId)
                    ->orWhere(function ($fallback) use ($rootEventId): void {
                        $fallback->whereNull('root_event_id')->whereKey($rootEventId);
                    });
            })
            ->get();
    }

    private function resolveImpactWindow(
        Collection $masters,
        Carbon $selectedStartsAt,
        string $scope,
        string $eventType,
        Carbon $cutoff,
    ): array {
        $from = match ($scope) {
            'this' => $selectedStartsAt->copy()->startOfMinute(),
            'this_and_following' => $selectedStartsAt->copy()->startOfMinute(),
            default => $eventType === EventType::Booking->value
                ? $cutoff->copy()
                : ($selectedStartsAt->lt($cutoff) ? $selectedStartsAt->copy()->startOfMinute() : $cutoff->copy()),
        };

        $isInfinite = $masters
            ->contains(fn (Event $master): bool => $this->masterHasNoFiniteEnd($master));

        if ($isInfinite) {
            return [$from, $from->copy()->addMonths(12)->endOfDay(), true];
        }

        $to = $masters
            ->map(fn (Event $master): ?Carbon => $this->masterFiniteEnd($master, $from))
            ->filter()
            ->max();

        if (! $to) {
            $to = $from->copy()->addMonths(12)->endOfDay();
        }

        return [$from, $to, false];
    }

    private function masterHasNoFiniteEnd(Event $master): bool
    {
        if (! $master->is_recurring || empty($master->recurrence_rule)) {
            return false;
        }

        $endsType = data_get($master->recurrence_rule, 'ends.type');

        return ! in_array($endsType, ['on', 'after'], true);
    }

    private function masterFiniteEnd(Event $master, Carbon $from): ?Carbon
    {
        if (! $master->is_recurring || empty($master->recurrence_rule) || ! $master->starts_at) {
            return $master->ends_at?->copy();
        }

        $rule = $master->recurrence_rule ?? [];
        $endsType = (string) data_get($rule, 'ends.type', 'never');

        if ($endsType === 'on' && filled(data_get($rule, 'ends.until'))) {
            return Carbon::parse((string) data_get($rule, 'ends.until'))->endOfDay();
        }

        if ($endsType === 'after' && filled(data_get($rule, 'ends.count'))) {
            $occurrences = $this->eventOccurrenceService->getOccurrenceDates(
                $master,
                $master->starts_at->copy()->startOfDay(),
                $master->starts_at->copy()->addYears(20)->endOfDay(),
            );

            return $occurrences->last()?->copy()->endOfDay();
        }

        return $from->copy()->addMonths(12)->endOfDay();
    }

    private function expandedLogicalOccurrences(Branch $branch, EventType $eventType, int $rootEventId, Carbon $from, Carbon $to): Collection
    {
        $logicalMasterIds = Event::query()
            ->where('branch_id', $branch->id)
            ->where('type', $eventType)
            ->whereNull('deleted_at')
            ->whereNull('recurrence_parent_id')
            ->where(function ($query) use ($rootEventId): void {
                $query->where('root_event_id', $rootEventId)
                    ->orWhere(function ($fallback) use ($rootEventId): void {
                        $fallback->whereNull('root_event_id')->whereKey($rootEventId);
                    });
            })
            ->pluck('id');

        if ($logicalMasterIds->isEmpty()) {
            return collect();
        }

        $events = Event::query()
            ->with(['services', 'bookingDetail', 'availabilityRuleDetail', 'groupDetail', 'participants'])
            ->where('branch_id', $branch->id)
            ->where('type', $eventType)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($rootEventId, $logicalMasterIds): void {
                $query
                    ->whereIn('id', $logicalMasterIds)
                    ->orWhereIn('recurrence_parent_id', $logicalMasterIds)
                    ->orWhere('root_event_id', $rootEventId)
                    ->orWhere(function ($fallback) use ($rootEventId): void {
                        $fallback->whereNull('root_event_id')->whereKey($rootEventId);
                    });
            })
            ->orderBy('starts_at')
            ->get();

        $expanded = $this->recurrenceExpansionService
            ->forEvents($events, $from, $to, false, $branch)
            ->filter(fn (array $occurrence): bool => (int) ($occurrence['root_event_id'] ?? 0) === $rootEventId)
            ->values();

        $mapped = $expanded
            ->map(fn (array $occurrence): array => $this->mapOccurrence($occurrence))
            ->values();

        $duplicateCount = $mapped->count() - $mapped->pluck('display_key')->unique()->count();

        if ($duplicateCount > 0) {
            throw ValidationException::withMessages([
                'display_key' => 'Duplicate display_key detected in impact preview.',
            ]);
        }

        return $mapped;
    }

    private function mapOccurrence(array $occurrence): array
    {
        /** @var Event $event */
        $event = $occurrence['event'];
        $startsAt = $occurrence['occurrence_starts_at'] ?? null;
        $endsAt = $occurrence['occurrence_ends_at'] ?? null;
        $originalStartsAt = $occurrence['occurrence_original_starts_at'] ?? $startsAt;

        return [
            'display_key' => (string) ($occurrence['display_key'] ?? sprintf('single:%d', (int) $event->id)),
            'root_event_id' => (int) ($occurrence['root_event_id'] ?? $event->root_event_id ?? $event->id),
            'active_master_id' => (int) ($occurrence['recurring_master_id'] ?? ($event->recurrence_parent_id ?? $event->id)),
            'original_start_at' => $originalStartsAt?->toIso8601String(),
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'status' => (string) ($event->status ?? 'confirmed'),
            'is_exception' => (bool) ($occurrence['is_override'] ?? false),
            'is_generated' => (bool) ($occurrence['is_occurrence'] ?? false) && ! (bool) ($occurrence['is_override'] ?? false),
            'event_id' => (int) $event->id,
            'event' => $event,
        ];
    }

    private function affectedOccurrencesByScope(
        Collection $allOccurrences,
        string $scope,
        Carbon $selectedStartsAt,
        string $selectedDisplayKey,
        array $selectedOccurrence,
        Event $event,
        int $logicalRootEventId,
        Carbon $cutoff,
        string $eventType,
    ): Collection {
        if ($scope === 'this') {
            $selected = $allOccurrences->firstWhere('display_key', $selectedDisplayKey)
                ?? $allOccurrences->first(function (array $occurrence) use ($selectedStartsAt): bool {
                    return filled($occurrence['starts_at'])
                        && Carbon::parse((string) $occurrence['starts_at'])->equalTo($selectedStartsAt);
                });

            if (! $selected) {
                $selected = [
                    'display_key' => $selectedDisplayKey,
                    'root_event_id' => $logicalRootEventId,
                    'active_master_id' => (int) ($selectedOccurrence['recurring_master_id'] ?? $event->id),
                    'original_start_at' => filled($selectedOccurrence['occurrence_original_starts_at'] ?? null)
                        ? Carbon::parse($selectedOccurrence['occurrence_original_starts_at'])->toIso8601String()
                        : $selectedStartsAt->toIso8601String(),
                    'starts_at' => $selectedStartsAt->toIso8601String(),
                    'ends_at' => filled($selectedOccurrence['occurrence_ends_at'] ?? null)
                        ? Carbon::parse($selectedOccurrence['occurrence_ends_at'])->toIso8601String()
                        : null,
                    'status' => 'confirmed',
                    'is_exception' => (bool) ($selectedOccurrence['is_override'] ?? false),
                    'is_generated' => (bool) ($selectedOccurrence['is_occurrence'] ?? false),
                    'event_id' => (int) $event->id,
                    'event' => $event,
                ];
            }

            return collect([$selected]);
        }

        $filtered = $allOccurrences->filter(function (array $occurrence) use ($scope, $selectedStartsAt, $cutoff, $eventType): bool {
            if (! filled($occurrence['starts_at'] ?? null)) {
                return false;
            }

            $startsAt = Carbon::parse((string) $occurrence['starts_at']);

            if ($scope === 'this_and_following') {
                if ($eventType === EventType::Booking->value) {
                    $from = $selectedStartsAt->gte($cutoff) ? $selectedStartsAt : $cutoff;

                    return $startsAt->gte($from);
                }

                return $startsAt->gte($selectedStartsAt);
            }

            if ($scope === 'all' && $eventType === EventType::Booking->value) {
                return $startsAt->gte($cutoff);
            }

            return $scope !== 'all' || $startsAt->gte($cutoff);
        });

        return $filtered->values();
    }

    private function pastOccurrencesNotModifiedCount(
        Branch $branch,
        EventType $eventType,
        int $rootEventId,
        Collection $masters,
        string $scope,
        Carbon $cutoff,
    ): int
    {
        if ($eventType !== EventType::Booking) {
            return 0;
        }

        if (! in_array($scope, ['all', 'this_and_following'], true)) {
            return 0;
        }

        $historyFrom = $masters
            ->pluck('starts_at')
            ->filter()
            ->map(fn ($startsAt) => Carbon::parse($startsAt)->startOfDay())
            ->min();

        if (! $historyFrom) {
            return 0;
        }

        $historyTo = $cutoff->copy()->subMinute();

        if ($historyTo->lt($historyFrom)) {
            return 0;
        }

        $allOccurrences = $this->expandedLogicalOccurrences(
            branch: $branch,
            eventType: $eventType,
            rootEventId: $rootEventId,
            from: $historyFrom,
            to: $historyTo,
        );

        return $allOccurrences
            ->filter(fn (array $occurrence): bool => filled($occurrence['starts_at'] ?? null))
            ->filter(fn (array $occurrence): bool => Carbon::parse((string) $occurrence['starts_at'])->lt($cutoff))
            ->count();
    }

    private function countConflictingBookings(Branch $branch, Collection $affectedAvailabilityOccurrences, Carbon $from, Carbon $to): int
    {
        if ($affectedAvailabilityOccurrences->isEmpty()) {
            return 0;
        }

        $bookingOccurrences = $this->recurrenceExpansionService
            ->forBranch($branch, $from, $to, [EventType::Booking], false)
            ->map(fn (array $occurrence): array => $this->mapOccurrence($occurrence));

        $conflicts = $bookingOccurrences
            ->filter(function (array $bookingOccurrence) use ($affectedAvailabilityOccurrences): bool {
                if (! filled($bookingOccurrence['starts_at'] ?? null) || ! filled($bookingOccurrence['ends_at'] ?? null)) {
                    return false;
                }

                $bookingStartsAt = Carbon::parse((string) $bookingOccurrence['starts_at']);
                $bookingEndsAt = Carbon::parse((string) $bookingOccurrence['ends_at']);

                return $affectedAvailabilityOccurrences->contains(function (array $availabilityOccurrence) use ($bookingStartsAt, $bookingEndsAt): bool {
                    if (! filled($availabilityOccurrence['starts_at'] ?? null) || ! filled($availabilityOccurrence['ends_at'] ?? null)) {
                        return false;
                    }

                    $availabilityStartsAt = Carbon::parse((string) $availabilityOccurrence['starts_at']);
                    $availabilityEndsAt = Carbon::parse((string) $availabilityOccurrence['ends_at']);

                    return $bookingStartsAt->lt($availabilityEndsAt) && $bookingEndsAt->gt($availabilityStartsAt);
                });
            })
            ->pluck('display_key')
            ->unique()
            ->count();

        return $conflicts;
    }

    private function countAffectedParticipants(Collection $affectedOccurrences): int
    {
        return $affectedOccurrences
            ->groupBy('display_key')
            ->sum(function (Collection $occurrenceGroup): int {
                /** @var Event|null $event */
                $event = $occurrenceGroup->first()['event'] ?? null;

                if (! $event) {
                    return 0;
                }

                return (int) $event->participants
                    ->where('status', 'confirmed')
                    ->count();
            });
    }

    private function buildMessage(
        string $scope,
        string $eventType,
        bool $isInfiniteSeries,
        int $affectedOccurrenceCount,
        int $pastNotModifiedCount,
        int $conflictingBookings,
        int $participantCount,
    ): string {
        $baseMessage = match ($scope) {
            'this' => 'Táto akcia ovplyvní 1 termín.',
            'this_and_following' => $isInfiniteSeries
                ? sprintf('Táto séria nemá koniec. Akcia ovplyvní všetky budúce termíny od vybraného dátumu. V najbližších 12 mesiacoch je to %d termínov.', $affectedOccurrenceCount)
                : sprintf('Táto akcia ovplyvní %d termínov od vybraného dátumu.', $affectedOccurrenceCount),
            default => $isInfiniteSeries
                ? sprintf('Táto séria nemá koniec. Akcia ovplyvní všetky termíny série. V najbližších 12 mesiacoch je to %d termínov.', $affectedOccurrenceCount)
                : sprintf('Táto akcia ovplyvní %d termínov v celej sérii.', $affectedOccurrenceCount),
        };

        if ($eventType === EventType::Booking->value && $pastNotModifiedCount > 0) {
            $bookingMessage = sprintf(
                'Táto akcia ovplyvní %d budúcich rezervácií. %d minulých rezervácií zostane v histórii.',
                $affectedOccurrenceCount,
                $pastNotModifiedCount,
            );

            if ($isInfiniteSeries && $scope !== 'this') {
                return $bookingMessage.' V najbližších 12 mesiacoch je to '.(string) $affectedOccurrenceCount.' termínov.';
            }

            return $bookingMessage;
        }

        if ($eventType === EventType::AvailabilityRule->value && $conflictingBookings > 0) {
            return sprintf(
                'Táto akcia ovplyvní %d termínov dostupnosti. %d existujúcich rezervácií môže byť v konflikte.',
                $affectedOccurrenceCount,
                $conflictingBookings,
            );
        }

        if ($eventType === EventType::GroupEvent->value) {
            return sprintf(
                'Táto akcia ovplyvní %d skupinových termínov a %d prihlásených účastníkov.',
                $affectedOccurrenceCount,
                $participantCount,
            );
        }

        return $baseMessage;
    }
}
