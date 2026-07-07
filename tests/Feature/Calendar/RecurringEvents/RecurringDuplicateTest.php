<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class RecurringDuplicateTest extends RecurringEventsTestCase
{
    public function test_duplicate_occurrence_creates_independent_single_event(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $exception = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 10:00:00',
            'ends_at' => '2026-07-13 11:00:00',
        ], $fixture['user']->id, 'this');

        $duplicate = $this->mutationService()->duplicate($exception, $fixture['user']->id);

        $this->assertNull($duplicate->recurrence_parent_id);
        $this->assertNull($duplicate->recurrence_original_starts_at);
        $this->assertFalse((bool) $duplicate->is_recurring);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertSame(5, count($snapshot));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_duplicate_moved_exception_keeps_duplicate_independent_from_parent_series(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $exception = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-15 14:00:00',
            'ends_at' => '2026-07-15 15:00:00',
        ], $fixture['user']->id, 'this');

        $duplicate = $this->mutationService()->duplicate($exception, $fixture['user']->id);

        $this->assertNull($duplicate->recurrence_parent_id);
        $this->assertSame('2026-07-15 14:00:00', $duplicate->starts_at?->format('Y-m-d H:i:s'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertOccurrenceExists($snapshot, '2026-07-15 16:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
