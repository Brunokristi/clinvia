<?php

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Jobs\SendEventNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_can_create_availability_rule_event_via_unified_api(): void
    {
        $fixture = $this->createCalendarFixture();

        $response = $this->actingAs($fixture['user'])->postJson(route('admin.branches.events.store', [
            $fixture['branch']->id,
        ]), [
            'type' => 'availability_rule',
            'status' => 'active',
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-20 12:00:00',
            'services' => [
                [
                    'service_id' => $fixture['service']->id,
                    'duration_minutes_snapshot' => 30,
                    'price_snapshot' => 30,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ],
            'recurrence_rule' => $this->weeklyRecurrence(['MO', 'WE']),
            'availability_rule_detail' => [
                'slot_interval_minutes' => 20,
                'buffer_before_minutes' => 5,
                'buffer_after_minutes' => 10,
                'capacity_rules' => [
                    'bookable_places' => 2,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'availability_rule')
            ->assertJsonPath('data.is_recurring', true)
            ->assertJsonPath('data.frontend.extendedProps.availability_rule.slot_interval_minutes', 20);

        $eventId = (int) $response->json('data.id');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'branch_id' => $fixture['branch']->id,
            'type' => 'availability_rule',
            'is_recurring' => true,
        ]);
        $this->assertDatabaseHas('availability_rule_event_details', [
            'event_id' => $eventId,
            'slot_interval_minutes' => 20,
        ]);
    }

    public function test_availability_rule_duration_is_not_constrained_by_service_duration(): void
    {
        $fixture = $this->createCalendarFixture();

        $response = $this->actingAs($fixture['user'])->postJson(route('admin.branches.events.store', [
            $fixture['branch']->id,
        ]), [
            'type' => 'availability_rule',
            'status' => 'active',
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-20 09:10:00',
            'services' => [
                [
                    'service_id' => $fixture['service']->id,
                    'duration_minutes_snapshot' => 30,
                    'price_snapshot' => 30,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ],
            'availability_rule_detail' => [
                'slot_interval_minutes' => 10,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'availability_rule');
    }

    public function test_can_add_and_remove_group_event_participant_via_unified_api(): void
    {
        Queue::fake();

        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'group_detail' => [
                'capacity' => 3,
                'reserved_places' => 0,
            ],
        ]);

        $addResponse = $this->actingAs($fixture['user'])->postJson(route('admin.branches.events.participants.store', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'participant_name' => 'API Participant',
            'participant_email' => 'api.participant@example.com',
            'participant_phone' => '+421911000111',
        ]);

        $addResponse->assertOk()
            ->assertJsonPath('participant.participant_name', 'API Participant')
            ->assertJsonPath('event.frontend.extendedProps.group_event.reserved_places', 1);

        $participantId = (int) $addResponse->json('participant.id');

        $this->assertDatabaseHas('group_event_participants', [
            'id' => $participantId,
            'event_id' => $event->id,
            'participant_email' => 'api.participant@example.com',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('group_event_details', [
            'event_id' => $event->id,
            'reserved_places' => 1,
        ]);
        Queue::assertPushed(SendEventNotificationJob::class);

        $removeResponse = $this->actingAs($fixture['user'])->deleteJson(route('admin.branches.events.participants.destroy', [
            $fixture['branch']->id,
            $event->id,
            $participantId,
        ]));

        $removeResponse->assertOk()
            ->assertJsonPath('event.frontend.extendedProps.group_event.reserved_places', 0);

        $this->assertDatabaseHas('group_event_participants', [
            'id' => $participantId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('group_event_details', [
            'event_id' => $event->id,
            'reserved_places' => 0,
        ]);
    }

    public function test_group_event_participant_endpoint_rejects_over_capacity_booking(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'group_detail' => [
                'capacity' => 1,
                'reserved_places' => 0,
            ],
        ]);

        $this->addGroupParticipant($event, [
            'participant_name' => 'Existing Participant',
            'participant_email' => 'existing.participant@example.com',
        ]);

        $response = $this->actingAs($fixture['user'])->postJson(route('admin.branches.events.participants.store', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'participant_name' => 'Overflow Participant',
            'participant_email' => 'overflow.participant@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['capacity']);
    }
}