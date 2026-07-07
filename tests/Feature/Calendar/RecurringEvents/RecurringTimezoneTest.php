<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class RecurringTimezoneTest extends RecurringEventsTestCase
{
    public function test_recurring_series_keeps_local_time_around_dst_transition(): void
    {
        $fixture = $this->createCalendarFixture();

        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => '2026-10-19 10:00:00',
            'ends_at' => '2026-10-19 11:00:00',
            'timezone' => 'Europe/Bratislava',
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'after',
                    'count' => 4,
                    'until' => null,
                ],
            ],
        ]);

        $snapshot = $this->calendarSnapshot($this->renderRange(
            $fixture['branch'],
            \Carbon\Carbon::parse('2026-10-01 00:00:00', 'Europe/Bratislava'),
            \Carbon\Carbon::parse('2026-11-20 00:00:00', 'Europe/Bratislava')
        ));

        $this->assertRenderedTimes($snapshot, [
            '2026-10-19 12:00',
            '2026-10-26 11:00',
            '2026-11-02 11:00',
            '2026-11-09 11:00',
        ]);

        $this->assertSame('Europe/Bratislava', $master->timezone);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
