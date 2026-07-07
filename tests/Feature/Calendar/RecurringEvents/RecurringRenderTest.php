<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class RecurringRenderTest extends RecurringEventsTestCase
{
    public function test_moved_exception_is_visible_when_actual_start_is_inside_range_even_if_original_is_outside(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => '2026-06-29 10:00:00',
            'ends_at' => '2026-06-29 11:00:00',
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => ['type' => 'after', 'count' => 6, 'until' => null],
            ],
        ]);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-06-29',
            'starts_at' => '2026-07-02 14:00:00',
            'ends_at' => '2026-07-02 15:00:00',
        ], $fixture['user']->id, 'this');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-02 16:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_moved_exception_is_hidden_when_actual_start_is_outside_range_even_if_original_is_inside(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-08-05 14:00:00',
            'ends_at' => '2026-08-05 15:00:00',
        ], $fixture['user']->id, 'this');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertOccurrenceMissing($snapshot, '2026-08-05 16:00');
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-20 12:00',
            '2026-07-27 12:00',
        ]);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
