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

    public function test_can_close_original_day_when_recurring_occurrence_is_moved_to_different_day(): void
    {
        $fixture = $this->createCalendarFixture();

        $event = $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-08 07:00:00'),
            'ends_at' => Carbon::parse('2026-07-08 09:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['WE'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-29']),
        ]);

        $this->createRecurringOverride($event, [
            'starts_at' => Carbon::parse('2026-07-06 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 12:00:00'),
            'recurrence_original_starts_at' => Carbon::parse('2026-07-08 07:00:00'),
            'recurrence_original_ends_at' => Carbon::parse('2026-07-08 09:00:00'),
        ]);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.disabled-days.store', $fixture['branch']->id), [
            'date' => '2026-07-08',
            'title' => 'Zatvorený deň',
            'type' => 'closed',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Deň bol zatvorený.');
        $this->assertDatabaseCount('branch_disabled_days', 1);
    }
}