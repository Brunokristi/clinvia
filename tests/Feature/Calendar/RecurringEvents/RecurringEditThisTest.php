<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;

class RecurringEditThisTest extends RecurringEventsTestCase
{
    public function test_edit_this_occurrence_creates_or_updates_single_exception_without_changing_master(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $old = [
            'starts_at' => $master->starts_at,
            'ends_at' => $master->ends_at,
            'recurrence_rule' => $master->recurrence_rule,
            'title' => $master->title,
        ];

        $exception = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 12:00:00',
            'ends_at' => '2026-07-13 13:00:00',
        ], $fixture['user']->id, 'this');

        $this->assertMasterUnchanged($master, $old);
        $this->assertSame($master->id, $exception->recurrence_parent_id);
        $this->assertOriginalStartNeverChanged($exception, '2026-07-13 12:00');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-13 14:00',
            '2026-07-20 12:00',
            '2026-07-27 12:00',
        ]);
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_edit_same_occurrence_twice_does_not_create_duplicate_exceptions(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 12:00:00',
            'ends_at' => '2026-07-13 13:00:00',
        ], $fixture['user']->id, 'this');

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 14:00:00',
            'ends_at' => '2026-07-13 15:00:00',
        ], $fixture['user']->id, 'this');

        $count = Event::query()
            ->where('recurrence_parent_id', $master->id)
            ->where('recurrence_original_starts_at', '2026-07-13 10:00:00')
            ->count();

        $this->assertSame(1, $count);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertOccurrenceExists($snapshot, '2026-07-13 16:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 14:00');
        $this->assertSame(4, count($snapshot));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
