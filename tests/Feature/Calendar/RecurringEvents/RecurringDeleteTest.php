<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;

class RecurringDeleteTest extends RecurringEventsTestCase
{
    public function test_delete_this_creates_cancelled_exception_and_hides_only_selected_occurrence(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-13',
        ], fn () => $this->mutationService()->delete($master, 'this'));

        $exception = Event::query()
            ->where('recurrence_parent_id', $master->id)
            ->where('recurrence_original_starts_at', '2026-07-13 10:00:00')
            ->first();

        $this->assertNotNull($exception);
        $this->assertSame('cancelled', $exception->status);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-20 12:00',
            '2026-07-27 12:00',
        ]);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_delete_this_and_following_trims_old_master_without_mass_exceptions(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-20',
        ], fn () => $this->mutationService()->delete($master, 'this_and_following'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
        ]);
        $this->assertSame(0, Event::query()->where('recurrence_parent_id', $master->id)->count());
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_delete_all_hides_entire_series(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->delete($master, 'series');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertSame([], $snapshot);
    }
}
