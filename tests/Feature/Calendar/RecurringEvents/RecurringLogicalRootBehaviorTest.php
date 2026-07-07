<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;

class RecurringLogicalRootBehaviorTest extends RecurringEventsTestCase
{
    public function test_repeated_this_and_following_on_split_group_series_does_not_duplicate_future_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => Carbon::parse('2026-07-06 07:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'TU', 'TH', 'FR'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-10',
                ],
            ],
            'group_detail' => [
                'capacity' => 5,
            ],
        ]);

        $initialSnapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($initialSnapshot, [
            '2026-07-06 09:00',
            '2026-07-07 09:00',
            '2026-07-09 09:00',
            '2026-07-10 09:00',
        ]);
        $this->assertSame(4, count($initialSnapshot));
        $this->assertNoDuplicateRenderedEvents($initialSnapshot);

        $splitMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-07',
            'starts_at' => '2026-07-07 08:00:00',
            'ends_at' => '2026-07-07 10:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $afterFirstSplit = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($afterFirstSplit, [
            '2026-07-06 09:00',
            '2026-07-07 10:00',
            '2026-07-09 10:00',
            '2026-07-10 10:00',
        ]);
        $this->assertSame(4, count($afterFirstSplit));
        $this->assertNoDuplicateRenderedEvents($afterFirstSplit);

        $rootEventId = (int) ($master->fresh()->root_event_id ?? $master->id);
        $activeMastersAfterFirstSplit = Event::query()
            ->whereNull('deleted_at')
            ->whereNull('recurrence_parent_id')
            ->where('root_event_id', $rootEventId)
            ->get();

        $this->assertSame(2, $activeMastersAfterFirstSplit->count());

        $this->mutationService()->update($splitMaster->fresh(), [
            'occurrence_date' => '2026-07-07',
            'starts_at' => '2026-07-07 09:00:00',
            'ends_at' => '2026-07-07 11:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $afterSecondSplit = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($afterSecondSplit, [
            '2026-07-06 09:00',
            '2026-07-07 11:00',
            '2026-07-09 11:00',
            '2026-07-10 11:00',
        ]);
        $this->assertSame(4, count($afterSecondSplit));
        $this->assertNoDuplicateRenderedEvents($afterSecondSplit);
        $this->assertOccurrenceMissing($afterSecondSplit, '2026-07-07 10:00');
        $this->assertOccurrenceMissing($afterSecondSplit, '2026-07-09 10:00');
        $this->assertOccurrenceMissing($afterSecondSplit, '2026-07-10 10:00');
    }

    public function test_delete_all_after_split_deletes_entire_logical_event_by_root_event_id(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => Carbon::parse('2026-07-06 07:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'TU', 'TH', 'FR'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-10',
                ],
            ],
            'group_detail' => [
                'capacity' => 5,
            ],
        ]);

        $splitMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-07',
            'starts_at' => '2026-07-07 08:00:00',
            'ends_at' => '2026-07-07 10:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $afterSplit = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($afterSplit, [
            '2026-07-06 09:00',
            '2026-07-07 10:00',
            '2026-07-09 10:00',
            '2026-07-10 10:00',
        ]);
        $this->assertSame(4, count($afterSplit));
        $this->assertNoDuplicateRenderedEvents($afterSplit);

        $rootEventId = (int) ($master->fresh()->root_event_id ?? $master->id);
        $this->assertSame($rootEventId, (int) ($splitMaster->fresh()->root_event_id ?? 0));

        $this->mutationService()->delete($splitMaster->fresh(), 'series');

        $afterDeleteAll = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertSame(0, count($afterDeleteAll));
        $this->assertNoDuplicateRenderedEvents($afterDeleteAll);

        $activeMasters = Event::query()
            ->whereNull('deleted_at')
            ->whereNull('recurrence_parent_id')
            ->where('root_event_id', $rootEventId)
            ->count();

        $this->assertSame(0, $activeMasters);
        $this->assertFalse(
            collect($this->renderRange($fixture['branch']))
                ->contains(fn (array $occurrence): bool => (int) ($occurrence['root_event_id'] ?? 0) === $rootEventId)
        );
    }

    public function test_delete_this_and_following_removes_future_occurrences_across_all_split_segments(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 11:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(6),
        ]);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->mutationService()->update($master->fresh(), [
            'occurrence_date' => '2026-08-03',
            'starts_at' => '2026-08-03 14:00:00',
            'ends_at' => '2026-08-03 15:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $afterSplits = $this->calendarSnapshot($this->renderRange(
            $fixture['branch'],
            $this->rangeStart(),
            Carbon::parse('2026-08-31 00:00:00', 'Europe/Bratislava'),
        ));

        $this->assertRenderedTimes($afterSplits, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
            '2026-07-20 14:00',
            '2026-07-27 14:00',
            '2026-08-03 16:00',
            '2026-08-10 16:00',
        ]);
        $this->assertSame(6, count($afterSplits));
        $this->assertNoDuplicateRenderedEvents($afterSplits);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-20',
        ], fn () => $this->mutationService()->delete($master->fresh(), 'this_and_following'));

        $afterDeleteFollowing = $this->calendarSnapshot($this->renderRange(
            $fixture['branch'],
            $this->rangeStart(),
            Carbon::parse('2026-08-31 00:00:00', 'Europe/Bratislava'),
        ));

        $this->assertRenderedTimes($afterDeleteFollowing, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
        ]);
        $this->assertSame(2, count($afterDeleteFollowing));
        $this->assertNoDuplicateRenderedEvents($afterDeleteFollowing);
        $this->assertOccurrenceMissing($afterDeleteFollowing, '2026-07-20 14:00');
        $this->assertOccurrenceMissing($afterDeleteFollowing, '2026-07-27 14:00');
        $this->assertOccurrenceMissing($afterDeleteFollowing, '2026-08-03 16:00');
        $this->assertOccurrenceMissing($afterDeleteFollowing, '2026-08-10 16:00');

        $rootEventId = (int) ($master->fresh()->root_event_id ?? $master->id);
        $futureOccurrences = collect($this->renderRange(
            $fixture['branch'],
            Carbon::parse('2026-07-20 00:00:00', 'Europe/Bratislava'),
            Carbon::parse('2026-09-01 00:00:00', 'Europe/Bratislava'),
        ))
            ->filter(fn (array $occurrence): bool => (int) ($occurrence['root_event_id'] ?? 0) === $rootEventId)
            ->count();

        $this->assertSame(0, $futureOccurrences);
    }

    public function test_scope_series_updates_all_split_segments_for_same_logical_root_and_preserves_cancelled_exceptions(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 11:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(6),
        ]);

        $splitMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->withRequestBody([
            'occurrence_date' => '2026-07-27',
        ], fn () => $this->mutationService()->cancel($splitMaster->fresh(), $fixture['user']->id, 'this'));

        $beforeSnapshot = $this->calendarSnapshot($this->renderRange(
            $fixture['branch'],
            $this->rangeStart(),
            Carbon::parse('2026-08-31 00:00:00', 'Europe/Bratislava'),
        ));

        $this->assertRenderedTimes($beforeSnapshot, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
            '2026-07-20 14:00',
            '2026-08-03 14:00',
            '2026-08-10 14:00',
        ]);

        $updated = $this->mutationService()->update($splitMaster->fresh(), [
            'starts_at' => '2026-08-10 11:00:00',
            'ends_at' => '2026-08-10 12:00:00',
        ], $fixture['user']->id, 'series');

        $afterSnapshot = $this->calendarSnapshot($this->renderRange(
            $fixture['branch'],
            $this->rangeStart(),
            Carbon::parse('2026-08-31 00:00:00', 'Europe/Bratislava'),
        ));

        $rootEventId = (int) ($updated->fresh()->root_event_id ?? $updated->id);

        $logicalEvents = collect($afterSnapshot)
            ->filter(fn (array $event): bool => (int) ($event['root_series_id'] ?? 0) === $rootEventId)
            ->values();

        $timeByDate = $logicalEvents
            ->mapWithKeys(fn (array $event): array => [substr((string) ($event['starts_at'] ?? ''), 0, 10) => substr((string) ($event['starts_at'] ?? ''), 11, 5)]);

        $this->assertSame($timeByDate['2026-08-10'] ?? null, $timeByDate['2026-07-06'] ?? null);
        $this->assertSame($timeByDate['2026-08-10'] ?? null, $timeByDate['2026-07-20'] ?? null);
        $this->assertSame($timeByDate['2026-08-10'] ?? null, $timeByDate['2026-08-03'] ?? null);
        $this->assertArrayNotHasKey('2026-07-27', $timeByDate->all());

        $cancelledException = Event::query()
            ->whereNotNull('recurrence_parent_id')
            ->whereDate('recurrence_original_starts_at', '2026-07-27')
            ->where('status', 'cancelled')
            ->first();

        $this->assertNotNull($cancelledException);
        $this->assertNoDuplicateRenderedEvents($afterSnapshot);
    }
}
