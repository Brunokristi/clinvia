<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class RecurringBoundaryTest extends RecurringEventsTestCase
{
    public function test_range_boundary_start_is_inclusive(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => '2026-07-01 00:00:00',
            'ends_at' => '2026-07-01 01:00:00',
            'timezone' => 'Europe/Bratislava',
        ]);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-01 02:00');
    }

    public function test_range_boundary_end_is_exclusive(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => '2026-08-01 00:00:00',
            'ends_at' => '2026-08-01 01:00:00',
            'timezone' => 'Europe/Bratislava',
        ]);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceMissing($snapshot, '2026-08-01 02:00');
    }

    public function test_overlapping_event_that_starts_before_range_and_ends_inside_range_is_included(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => '2026-06-30 23:30:00',
            'ends_at' => '2026-07-01 00:30:00',
            'timezone' => 'Europe/Bratislava',
        ]);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-01 01:30');
    }

    public function test_overlapping_event_that_starts_inside_range_and_ends_after_range_is_included(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => '2026-07-31 21:30:00',
            'ends_at' => '2026-07-31 22:30:00',
            'timezone' => 'Europe/Bratislava',
        ]);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-31 23:30');
    }

    public function test_initial_render_has_no_persisted_generated_instances(): void
    {
        $fixture = $this->createCalendarFixture();
        $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->renderRange($fixture['branch']);

        $this->assertNoPersistedGeneratedInstances();
    }
}