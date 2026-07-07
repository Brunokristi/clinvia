<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class RecurringWeekdayMoveTest extends RecurringEventsTestCase
{
    public function test_reschedule_this_to_another_weekday_creates_only_exception_and_keeps_future_pattern(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-02 08:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-02 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['TH'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-30',
                ],
            ],
        ]);

        $this->mutationService()->reschedule(
            $master,
            Carbon::parse('2026-07-13 08:00:00', 'Europe/Bratislava'),
            Carbon::parse('2026-07-13 09:00:00', 'Europe/Bratislava'),
            $fixture['user']->id,
            'this',
            Carbon::parse('2026-07-16', 'Europe/Bratislava'),
        );

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceMissing($snapshot, '2026-07-16 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-13 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-23 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-30 10:00');
        $this->assertSame(['TH'], data_get($master->fresh()->recurrence_rule, 'weekdays'));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_this_and_following_single_weekday_move_replaces_byday_and_starts_new_segment_at_new_start(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-02 08:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-02 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['TH'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-30',
                ],
            ],
        ]);

        $newMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-16',
            'starts_at' => '2026-07-20 08:00:00',
            'ends_at' => '2026-07-20 09:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->assertSame(['TH'], data_get($master->fresh()->recurrence_rule, 'weekdays'));
        $this->assertSame(['MO'], data_get($newMaster->fresh()->recurrence_rule, 'weekdays'));
        $this->assertSame('2026-07-20 08:00:00', $newMaster->fresh()->starts_at?->format('Y-m-d H:i:s'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-02 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-09 10:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-16 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-20 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-27 10:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-23 10:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_this_and_following_multi_weekday_move_replaces_only_selected_weekday(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-06 08:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'TU', 'TH', 'FR'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-17',
                ],
            ],
        ]);

        $newMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-09',
            'starts_at' => '2026-07-08 08:00:00',
            'ends_at' => '2026-07-08 09:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->assertSame(['MO', 'TU', 'WE', 'FR'], data_get($newMaster->fresh()->recurrence_rule, 'weekdays'));
        $this->assertNoDuplicateRenderedEvents($this->calendarSnapshot($this->renderRange($fixture['branch'])));
    }

    public function test_scope_all_weekday_move_updates_all_active_masters_for_logical_root(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-02 08:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-02 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['TH'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-08-06',
                ],
            ],
        ]);

        $splitMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-16',
            'starts_at' => '2026-07-16 09:00:00',
            'ends_at' => '2026-07-16 10:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->mutationService()->update($splitMaster->fresh(), [
            'occurrence_date' => '2026-07-23',
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-20 10:00:00',
        ], $fixture['user']->id, 'series');

        $rootEventId = (int) ($master->fresh()->root_event_id ?? $master->id);

        $activeMasters = \App\Modules\Calendar\Models\Event::query()
            ->whereNull('recurrence_parent_id')
            ->whereNull('deleted_at')
            ->where('root_event_id', $rootEventId)
            ->get();

        $this->assertTrue(
            $activeMasters->every(fn ($event) => data_get($event->recurrence_rule, 'weekdays') === ['MO']),
            'All active masters under root_event_id must use moved weekday after scope=all.'
        );

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $logicalSnapshot = collect($snapshot)
            ->filter(fn (array $occurrence): bool => (int) ($occurrence['root_series_id'] ?? 0) === $rootEventId)
            ->values();

        $hasThursday = $logicalSnapshot->contains(function (array $occurrence): bool {
            $startsAt = $occurrence['starts_at'] ?? null;

            return $startsAt !== null
                && Carbon::parse($startsAt, 'Europe/Bratislava')->dayOfWeekIso === 4;
        });

        $this->assertFalse($hasThursday);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_this_and_following_weekday_move_is_blocked_if_new_weekday_already_exists(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-06 08:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'TU', 'TH', 'FR'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-31',
                ],
            ],
        ]);

        $this->expectException(ValidationException::class);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-09',
            'starts_at' => '2026-07-13 08:00:00',
            'ends_at' => '2026-07-13 09:00:00',
        ], $fixture['user']->id, 'this_and_following');
    }

    public function test_this_and_following_weekday_move_is_blocked_when_new_start_overlaps_retained_old_segment(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-02 08:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-02 09:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['TH'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-30',
                ],
            ],
        ]);

        $this->expectException(ValidationException::class);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-16',
            'starts_at' => '2026-07-08 08:00:00',
            'ends_at' => '2026-07-08 09:00:00',
        ], $fixture['user']->id, 'this_and_following');
    }
}
