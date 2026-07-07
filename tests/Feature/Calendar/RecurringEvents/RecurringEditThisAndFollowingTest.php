<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;

class RecurringEditThisAndFollowingTest extends RecurringEventsTestCase
{
    public function test_edit_this_and_following_splits_series_from_third_occurrence_without_duplicates(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $newMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->assertNotSame($master->id, $newMaster->id);
        $this->assertSeriesWasSplit($master, $newMaster, '2026-07-20 12:00:00');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
            '2026-07-20 14:00',
            '2026-07-27 14:00',
        ]);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_edit_this_and_following_from_first_occurrence_updates_source_series_without_overlap(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $newMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-06',
            'starts_at' => '2026-07-06 12:00:00',
            'ends_at' => '2026-07-06 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertSame(4, count($snapshot));
        $this->assertOccurrenceExists($snapshot, '2026-07-06 14:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-13 14:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-06 12:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');

        $this->assertSame($master->id, $newMaster->fresh()->id);
        $this->assertSame(1, Event::query()->whereNull('recurrence_parent_id')->count());
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_repeated_this_and_following_splits_active_master_not_original_root_and_does_not_duplicate(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(6),
        ]);

        $secondMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $thirdMaster = $this->mutationService()->update($master->fresh(), [
            'occurrence_date' => '2026-08-03',
            'starts_at' => '2026-08-03 13:00:00',
            'ends_at' => '2026-08-03 14:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->assertSame('2026-07-13', data_get($master->fresh()->recurrence_rule, 'ends.until'));
        $this->assertSame('2026-07-27', data_get($secondMaster->fresh()->recurrence_rule, 'ends.until'));
        $this->assertSame($secondMaster->id, $thirdMaster->fresh()->split_from_event_id);

        $snapshot = $this->calendarSnapshot($this->renderRange(
            $fixture['branch'],
            $this->rangeStart(),
            Carbon::parse('2026-08-31 00:00:00', 'Europe/Bratislava'),
        ));

        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
            '2026-07-20 14:00',
            '2026-07-27 14:00',
            '2026-08-03 15:00',
            '2026-08-10 15:00',
        ]);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
