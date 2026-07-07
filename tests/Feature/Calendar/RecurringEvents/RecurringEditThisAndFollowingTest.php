<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;

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

    public function test_edit_this_and_following_from_first_occurrence_replaces_effective_series(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $newMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-06',
            'starts_at' => '2026-07-06 12:00:00',
            'ends_at' => '2026-07-06 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertSame(8, count($snapshot));
        $this->assertOccurrenceExists($snapshot, '2026-07-06 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-06 14:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-13 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-13 14:00');

        $this->assertNotNull($newMaster->fresh());
        $this->assertTrue(Event::query()->whereKey($master->id)->exists());
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
