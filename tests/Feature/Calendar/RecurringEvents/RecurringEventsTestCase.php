<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Models\Branch;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;
use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

abstract class RecurringEventsTestCase extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    protected function rangeStart(): Carbon
    {
        return Carbon::parse('2026-07-01 00:00:00', 'Europe/Bratislava');
    }

    protected function rangeEnd(): Carbon
    {
        return Carbon::parse('2026-08-01 00:00:00', 'Europe/Bratislava');
    }

    protected function baseWeeklyCountRecurrence(int $count = 4): array
    {
        return [
            'frequency' => 'weekly',
            'interval' => 1,
            'weekdays' => ['MO'],
            'ends' => [
                'type' => 'after',
                'count' => $count,
                'until' => null,
            ],
        ];
    }

    protected function createBaseRecurringMaster(array $fixture, EventType $type = EventType::Booking, array $overrides = []): Event
    {
        $base = [
            'title' => 'Therapy',
            'starts_at' => Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 11:00:00', 'Europe/Bratislava'),
            'timezone' => 'Europe/Bratislava',
            'is_recurring' => true,
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(4),
            'metadata' => [
                'series_uuid' => (string) Str::uuid(),
            ],
        ];

        $payload = array_replace($base, $overrides);

        return match ($type) {
            EventType::AvailabilityRule => $this->createAvailabilityRuleEvent($fixture, $payload),
            EventType::GroupEvent => $this->createGroupEvent($fixture, $payload),
            default => $this->createBookingEvent($fixture, $payload),
        };
    }

    protected function renderRange(Branch $branch, ?Carbon $start = null, ?Carbon $end = null, bool $includeCancelled = false): Collection
    {
        return app(RecurrenceExpansionService::class)->forBranch(
            $branch,
            $start ?? $this->rangeStart(),
            $end ?? $this->rangeEnd(),
            null,
            $includeCancelled,
        );
    }

    protected function calendarSnapshot(Collection $events): array
    {
        return $events
            ->map(function (array $occurrence): array {
                /** @var Event $event */
                $event = $occurrence['event'];
                $rootSeriesId = (int) ($occurrence['root_event_id'] ?? $event->id);
                $originalStartAt = ($occurrence['occurrence_original_starts_at'] ?? null)?->copy();
                $startsAt = ($occurrence['occurrence_starts_at'] ?? null)?->copy();
                $endsAt = ($occurrence['occurrence_ends_at'] ?? null)?->copy();
                $isException = (bool) ($occurrence['is_override'] ?? false);
                $isGenerated = (bool) ($occurrence['is_occurrence'] ?? false) && ! $isException;

                $originalStartAtString = $originalStartAt
                    ? $originalStartAt->setTimezone($event->timezone ?? config('app.timezone'))->format('Y-m-d H:i')
                    : null;

                $displayKey = $originalStartAtString !== null
                    ? $rootSeriesId . ':' . $originalStartAtString
                    : 'single:' . $event->id;

                return [
                    'id' => (int) $event->id,
                    'title' => (string) ($event->title ?? $event->display_title ?? ''),
                    'event_type' => $event->type->value,
                    'starts_at' => $startsAt?->setTimezone($event->timezone ?? config('app.timezone'))->format('Y-m-d H:i'),
                    'ends_at' => $endsAt?->setTimezone($event->timezone ?? config('app.timezone'))->format('Y-m-d H:i'),
                    'timezone' => (string) ($event->timezone ?? config('app.timezone')),
                    'parent_id' => $event->recurrence_parent_id,
                    'root_series_id' => $rootSeriesId,
                    'original_start_at' => $originalStartAtString,
                    'status' => (string) $event->status,
                    'is_exception' => $isException,
                    'is_generated' => $isGenerated,
                    'display_key' => $displayKey,
                ];
            })
            ->sortBy(fn (array $item) => ($item['starts_at'] ?? '9999-12-31 23:59') . '|' . $item['display_key'])
            ->values()
            ->all();
    }

    protected function assertNoDuplicateRenderedEvents(array $events): void
    {
        $displayKeys = array_column($events, 'display_key');
        $this->assertCount(
            count(array_unique($displayKeys)),
            $displayKeys,
            'Duplicate display_key detected in rendered events snapshot.'
        );

        $seriesOriginalKeys = collect($events)
            ->filter(fn (array $event) => $event['original_start_at'] !== null)
            ->map(fn (array $event) => $event['root_series_id'] . ':' . $event['original_start_at'])
            ->values()
            ->all();

        $this->assertCount(
            count(array_unique($seriesOriginalKeys)),
            $seriesOriginalKeys,
            'Duplicate root_series_id + original_start_at detected in rendered events snapshot.'
        );

        collect($events)
            ->groupBy('display_key')
            ->each(function (Collection $group): void {
                if ($group->count() > 1) {
                    $hasGenerated = $group->contains(fn (array $item) => (bool) $item['is_generated']);
                    $hasException = $group->contains(fn (array $item) => (bool) $item['is_exception']);

                    $this->assertFalse(
                        $hasGenerated && $hasException,
                        'A generated instance and an exception were rendered together for the same display_key.'
                    );
                }
            });
    }

    protected function assertRenderedTimes(array $events, array $expectedTimes): void
    {
        $actual = collect($events)
            ->pluck('starts_at')
            ->filter()
            ->sort()
            ->values()
            ->all();

        $expected = collect($expectedTimes)->sort()->values()->all();

        $this->assertSame($expected, $actual);
    }

    protected function assertOccurrenceExists(array $events, string $startsAt): void
    {
        $this->assertTrue(
            collect($events)->contains(fn (array $event) => $event['starts_at'] === $startsAt),
            'Expected occurrence not found at ' . $startsAt
        );
    }

    protected function assertOccurrenceMissing(array $events, string $startsAt): void
    {
        $this->assertFalse(
            collect($events)->contains(fn (array $event) => $event['starts_at'] === $startsAt),
            'Unexpected occurrence found at ' . $startsAt
        );
    }

    protected function assertOriginalStartNeverChanged(Event $exception, string $expectedOriginalStart): void
    {
        $this->assertSame(
            $expectedOriginalStart,
            $exception->fresh()->recurrence_original_starts_at?->setTimezone('Europe/Bratislava')->format('Y-m-d H:i')
        );
    }

    protected function assertMasterUnchanged(Event $master, array $oldValues): void
    {
        $fresh = $master->fresh();

        foreach ($oldValues as $field => $value) {
            $actual = data_get($fresh, $field);

            if ($actual instanceof Carbon) {
                $actual = $actual->format('Y-m-d H:i:s');
            }

            if ($value instanceof Carbon) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $this->assertSame($value, $actual, 'Master field changed unexpectedly: ' . $field);
        }
    }

    protected function assertOnlyOneMasterExistsForSeries(string $seriesId): void
    {
        $count = Event::query()
            ->whereNull('deleted_at')
            ->whereNull('recurrence_parent_id')
            ->whereRaw("metadata->>'series_uuid' = ?", [$seriesId])
            ->count();

        $this->assertSame(1, $count);
    }

    protected function assertSeriesWasSplit(Event $oldMaster, Event $newMaster, string $splitOriginalStart): void
    {
        $oldUntil = data_get($oldMaster->fresh()->recurrence_rule, 'ends.until');
        $this->assertNotNull($oldUntil);
        $this->assertTrue(
            Carbon::parse($oldUntil)->lt(Carbon::parse($splitOriginalStart)->startOfDay()),
            'Old master was not trimmed before split original start.'
        );

        $this->assertTrue(
            $newMaster->fresh()->starts_at?->gte(Carbon::parse($splitOriginalStart)),
            'New master does not start at or after the split point.'
        );

        $snapshot = $this->calendarSnapshot($this->renderRange($oldMaster->branch));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    protected function withRequestBody(array $input, callable $callback)
    {
        request()->replace($input);

        try {
            return $callback();
        } finally {
            request()->replace([]);
        }
    }

    protected function mutationService(): EventMutationService
    {
        return app(EventMutationService::class);
    }
}
