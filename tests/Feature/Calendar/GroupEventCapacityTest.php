<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventReadAdapterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class GroupEventCapacityTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_group_event_create_rejects_patients_payload(): void
    {
        $fixture = $this->createCalendarFixture();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-07-20 14:00:00',
            'ends_at' => '2026-07-20 15:00:00',
            'capacity' => 5,
            'patients' => [
                [
                    'patient_name' => 'Should Fail',
                    'patient_email' => 'should.fail@example.com',
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['patients']);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_recurring_group_event_participant_requires_occurrence_context(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 15:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
        ]);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.bookings.store', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'patient_name' => 'Missing Occurrence',
            'patient_email' => 'missing.occurrence@example.com',
        ]);

        $response->assertSessionHasErrors(['occurrence_starts_at']);
        $this->assertDatabaseMissing('group_event_participants', [
            'participant_email' => 'missing.occurrence@example.com',
        ]);
    }

    public function test_recurring_group_event_participant_is_added_to_single_materialized_occurrence_only(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 15:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
        ]);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.bookings.store', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'patient_name' => 'Occurrence Patient',
            'patient_email' => 'occurrence.patient@example.com',
            'occurrence_starts_at' => '2026-07-13 14:00:00',
            'occurrence_ends_at' => '2026-07-13 15:00:00',
        ]);

        $response->assertSessionHasNoErrors();

        $override = Event::query()
            ->where('recurrence_parent_id', $event->id)
            ->where('starts_at', '2026-07-13 14:00:00')
            ->first();

        $this->assertNotNull($override);
        $this->assertDatabaseHas('group_event_participants', [
            'event_id' => $override->id,
            'participant_email' => 'occurrence.patient@example.com',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseMissing('group_event_participants', [
            'event_id' => $event->id,
            'participant_email' => 'occurrence.patient@example.com',
        ]);
    }

    public function test_recurring_group_event_occurrence_update_succeeds_without_recurrence_rule_mutation(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 15:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
            'group_detail' => [
                'capacity' => 3,
            ],
        ]);

        $response = $this->actingAs($fixture['user'])->put(route('branches.booking.capacity-windows.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'capacity' => 5,
            'starts_at' => '2026-07-13 14:00:00',
            'ends_at' => '2026-07-13 15:00:00',
            'update_scope' => 'occurrence',
            'from_date' => '2026-07-13',
        ]);

        $response->assertSessionHasNoErrors();

        $override = Event::query()
            ->where('recurrence_parent_id', $event->id)
            ->where('starts_at', '2026-07-13 14:00:00')
            ->first();

        $this->assertNotNull($override);
        $this->assertDatabaseHas('group_event_details', [
            'event_id' => $override->id,
            'capacity' => 5,
        ]);
    }

    public function test_group_event_recurrence_edit_is_applied_even_if_occurrence_scope_is_sent(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 15:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
            'group_detail' => [
                'capacity' => 3,
            ],
        ]);

        $response = $this->actingAs($fixture['user'])->put(route('branches.booking.capacity-windows.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'capacity' => 3,
            'starts_at' => '2026-07-06 14:00:00',
            'ends_at' => '2026-07-06 15:00:00',
            'update_scope' => 'occurrence',
            'from_date' => '2026-07-06',
            'recurrence' => $this->weeklyRecurrence(['MO', 'WE'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-08-31']),
        ]);

        $response->assertSessionHasNoErrors();

        $event->refresh();

        $this->assertSame(['MO', 'WE'], $event->recurrence_rule['weekdays'] ?? []);
        $this->assertSame('2026-08-31', $event->recurrence_rule['ends']['until'] ?? null);
    }

    public function test_recurring_group_event_occurrences_have_unique_calendar_event_ids(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 15:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO', 'WE'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-15']),
            'group_detail' => [
                'capacity' => 5,
            ],
        ]);

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-07-20')->endOfDay(),
        );

        $capacityWindows = collect($payload['calendarCapacityWindows'] ?? []);

        $this->assertGreaterThan(1, $capacityWindows->count());
        $this->assertCount(
            $capacityWindows->count(),
            $capacityWindows->pluck('calendar_event_id')->filter()->unique()->values()->all(),
        );
    }

    public function test_group_event_bridge_store_creates_series_from_ui_recurrence_payload(): void
    {
        $fixture = $this->createCalendarFixture();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-07-06 14:00:00',
            'ends_at' => '2026-07-06 15:00:00',
            'capacity' => 6,
            'repeats' => true,
            'repeat_every' => 1,
            'repeat_unit' => 'weeks',
            'repeat_ends_on' => null,
            'recurrence' => [
                'mode' => 'weekly',
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'never',
                    'count' => null,
                    'until' => null,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $event = Event::query()->first();
        $this->assertNotNull($event);
        $this->assertTrue((bool) $event->is_recurring);
        $this->assertSame('weekly', $event->recurrence_rule['frequency'] ?? null);

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-06')->startOfDay(),
            Carbon::parse('2026-08-03')->endOfDay(),
        );

        $capacityWindows = collect($payload['calendarCapacityWindows'] ?? []);
        $this->assertGreaterThanOrEqual(4, $capacityWindows->count());
    }
}