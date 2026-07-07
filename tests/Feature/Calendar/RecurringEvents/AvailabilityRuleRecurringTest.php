<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;

class AvailabilityRuleRecurringTest extends RecurringEventsTestCase
{
    public function test_availability_rule_delete_this_hides_only_one_occurrence(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::AvailabilityRule, [
            'starts_at' => '2026-07-06 08:00:00',
            'ends_at' => '2026-07-06 14:00:00',
        ]);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-13',
        ], fn () => $this->mutationService()->delete($master, 'this'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-06 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-20 10:00');
        $this->assertOccurrenceExists($snapshot, '2026-07-27 10:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_availability_rule_split_from_date_keeps_old_and_new_segments_without_overlap(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::AvailabilityRule, [
            'starts_at' => '2026-07-06 08:00:00',
            'ends_at' => '2026-07-06 14:00:00',
        ]);

        $newMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-20 15:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->assertNotSame($master->id, $newMaster->id);
        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
