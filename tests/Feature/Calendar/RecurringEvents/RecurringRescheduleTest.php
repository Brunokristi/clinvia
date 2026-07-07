<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;

class RecurringRescheduleTest extends RecurringEventsTestCase
{
    public function test_reschedule_this_occurrence_to_another_day_keeps_original_start_and_renders_once(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $exception = $this->mutationService()->reschedule(
            $master,
            Carbon::parse('2026-07-15 14:00:00', 'Europe/Bratislava'),
            Carbon::parse('2026-07-15 15:00:00', 'Europe/Bratislava'),
            $fixture['user']->id,
            'this',
            Carbon::parse('2026-07-13', 'Europe/Bratislava'),
        );

        $this->assertOriginalStartNeverChanged($exception, '2026-07-13 12:00');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-15 16:00',
            '2026-07-20 12:00',
            '2026-07-27 12:00',
        ]);
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_delete_already_moved_exception_updates_same_exception_to_cancelled(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->reschedule(
            $master,
            Carbon::parse('2026-07-15 14:00:00', 'Europe/Bratislava'),
            Carbon::parse('2026-07-15 15:00:00', 'Europe/Bratislava'),
            $fixture['user']->id,
            'this',
            Carbon::parse('2026-07-13', 'Europe/Bratislava'),
        );

        $this->withRequestBody([
            'occurrence_date' => '2026-07-13',
        ], fn () => $this->mutationService()->delete($master, 'this'));

        $count = Event::query()
            ->where('recurrence_parent_id', $master->id)
            ->where('recurrence_original_starts_at', '2026-07-13 10:00:00')
            ->count();

        $this->assertSame(1, $count);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-15 16:00');
        $this->assertSame(3, count($snapshot));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
