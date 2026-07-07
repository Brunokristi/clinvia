<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class RecurringCountUntilTest extends RecurringEventsTestCase
{
    public function test_split_series_with_count_keeps_total_visible_occurrence_count(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertSame(4, count($snapshot));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_split_series_with_until_trims_old_until_before_split(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-27',
                ],
            ],
        ]);

        $newMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->assertSeriesWasSplit($master, $newMaster, '2026-07-20 12:00:00');
    }
}
