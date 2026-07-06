<?php

namespace Tests\Feature\Calendar;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class DisabledDayGuardTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_cannot_close_day_when_recurring_event_occurs_on_that_day(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
        ]);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.disabled-days.store', $fixture['branch']->id), [
            'date' => '2026-07-20',
            'title' => 'Zatvorený deň',
            'type' => 'closed',
        ]);

        $response->assertSessionHasErrors(['date']);
        $this->assertDatabaseCount('branch_disabled_days', 0);
    }
}