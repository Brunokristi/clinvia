<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;

class RecurringCreateTest extends RecurringEventsTestCase
{
    public function test_create_single_event_and_recurring_master_render_correctly(): void
    {
        $fixture = $this->createCalendarFixture();

        $single = $this->createBookingEvent($fixture, [
            'title' => 'Single Therapy',
            'starts_at' => '2026-07-08 09:00:00',
            'ends_at' => '2026-07-08 10:00:00',
            'timezone' => 'Europe/Bratislava',
        ]);

        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceExists($snapshot, '2026-07-06 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-13 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-20 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-27 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-08 11:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);

        $this->assertSame(0, Event::query()->where('recurrence_parent_id', $master->id)->count());
        $this->assertSame(1, Event::query()->whereKey($single->id)->count());
        $this->assertSame(1, Event::query()->whereKey($master->id)->count());
    }
}
