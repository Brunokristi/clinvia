<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class RecurringSplitTest extends RecurringEventsTestCase
{
    public function test_split_with_future_exception_resets_future_exception_by_default(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-27',
            'starts_at' => '2026-07-28 14:00:00',
            'ends_at' => '2026-07-28 15:00:00',
        ], $fixture['user']->id, 'this');

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-27 14:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-28 16:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_cancelled_exception_before_split_stays_in_old_series_and_new_series_continues(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-13',
        ], fn () => $this->mutationService()->delete($master, 'this'));

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-06 12:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-20 14:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-27 14:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
