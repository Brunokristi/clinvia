<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;

class RecurringEditAllTest extends RecurringEventsTestCase
{
    public function test_edit_all_updates_master_without_split_or_exceptions(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $updated = $this->mutationService()->update($master, [
            'starts_at' => '2026-07-06 12:00:00',
            'ends_at' => '2026-07-06 13:00:00',
        ], $fixture['user']->id, 'series');

        $this->assertSame($master->id, $updated->id);
        $this->assertSame(0, Event::query()->where('recurrence_parent_id', $master->id)->count());
        $this->assertOnlyOneMasterExistsForSeries((string) data_get($master->metadata, 'series_uuid'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 14:00',
            '2026-07-13 14:00',
            '2026-07-20 14:00',
            '2026-07-27 14:00',
        ]);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_edit_all_keeps_existing_exception_priority(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 14:00:00',
            'ends_at' => '2026-07-13 15:00:00',
        ], $fixture['user']->id, 'this');

        $this->mutationService()->update($master, [
            'title' => 'Therapy Updated',
            'starts_at' => '2026-07-06 12:00:00',
            'ends_at' => '2026-07-06 13:00:00',
        ], $fixture['user']->id, 'series');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-13 16:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
