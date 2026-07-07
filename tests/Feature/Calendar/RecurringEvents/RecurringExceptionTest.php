<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;

class RecurringExceptionTest extends RecurringEventsTestCase
{
    public function test_cancelled_exception_hides_generated_occurrence_and_never_renders_cancelled_row(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-13',
        ], fn () => $this->mutationService()->delete($master, 'this'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);

        $cancelled = Event::query()
            ->where('recurrence_parent_id', $master->id)
            ->where('recurrence_original_starts_at', '2026-07-13 10:00:00')
            ->first();

        $this->assertSame('cancelled', $cancelled?->status);
    }

    public function test_duplicate_bug_guard_generated_and_exception_for_same_original_start_are_not_rendered_together(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-15 14:00:00',
            'ends_at' => '2026-07-15 15:00:00',
        ], $fixture['user']->id, 'this');

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-15 16:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
