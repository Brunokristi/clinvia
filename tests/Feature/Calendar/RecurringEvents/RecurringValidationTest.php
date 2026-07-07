<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use Illuminate\Validation\ValidationException;

class RecurringValidationTest extends RecurringEventsTestCase
{
    public function test_recurring_series_is_persisted_with_timezone(): void
    {
        $fixture = $this->createCalendarFixture();

        $event = $this->mutationService()->create($fixture['branch'], [
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => '2026-07-06 10:00:00',
            'ends_at' => '2026-07-06 11:00:00',
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(4),
            'services' => [[
                'service_id' => $fixture['service']->id,
                'sort_order' => 0,
                'quantity' => 1,
            ]],
            'booking_detail' => [
                'patient_name' => 'Timezone Missing',
            ],
        ], $fixture['user']->id);

        $this->assertSame(config('app.timezone'), $event->timezone);
    }

    public function test_duplicate_recurring_master_creates_independent_recurring_series(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $duplicate = $this->mutationService()->duplicate($master, $fixture['user']->id);

        $this->assertTrue((bool) $duplicate->is_recurring);
        $this->assertNull($duplicate->recurrence_parent_id);
        $this->assertNull($duplicate->recurrence_original_starts_at);
        $this->assertSame($master->recurrence_rule, $duplicate->recurrence_rule);
        $this->assertNotSame(
            (string) data_get($master->metadata, 'series_uuid'),
            (string) data_get($duplicate->metadata, 'series_uuid'),
        );

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));

        $this->assertSame(8, count($snapshot));
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}