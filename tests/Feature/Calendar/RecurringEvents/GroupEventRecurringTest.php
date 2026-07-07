<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;

class GroupEventRecurringTest extends RecurringEventsTestCase
{
    public function test_group_event_participants_stay_on_single_occurrence_only(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => '2026-07-06 14:00:00',
            'ends_at' => '2026-07-06 15:00:00',
            'group_detail' => [
                'capacity' => 5,
            ],
        ]);

        $occurrence = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 14:00:00',
            'ends_at' => '2026-07-13 15:00:00',
        ], $fixture['user']->id, 'this');

        $this->addGroupParticipant($occurrence, [
            'participant_name' => 'Occurrence Participant',
            'participant_email' => 'occurrence.participant@example.com',
        ]);

        $this->assertSame(1, $occurrence->fresh()->participants()->where('status', 'confirmed')->count());
        $this->assertSame(0, $master->fresh()->participants()->where('status', 'confirmed')->count());
    }

    public function test_group_event_duplicate_does_not_copy_participants(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => '2026-07-06 14:00:00',
            'ends_at' => '2026-07-06 15:00:00',
        ]);

        $occurrence = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 14:00:00',
            'ends_at' => '2026-07-13 15:00:00',
        ], $fixture['user']->id, 'this');

        $this->addGroupParticipant($occurrence, [
            'participant_name' => 'Participant To Keep',
            'participant_email' => 'participant.keep@example.com',
        ]);

        $duplicate = $this->mutationService()->duplicate($occurrence, $fixture['user']->id);

        $this->assertSame(1, $occurrence->fresh()->participants()->where('status', 'confirmed')->count());
        $this->assertSame(0, $duplicate->fresh()->participants()->where('status', 'confirmed')->count());
    }

    public function test_group_event_occurrence_update_does_not_create_duplicate_override_rows(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => '2026-07-06 14:00:00',
            'ends_at' => '2026-07-06 15:00:00',
        ]);

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 16:00:00',
            'ends_at' => '2026-07-13 17:00:00',
        ], $fixture['user']->id, 'this');

        $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 16:30:00',
            'ends_at' => '2026-07-13 17:30:00',
        ], $fixture['user']->id, 'this');

        $count = Event::query()
            ->where('recurrence_parent_id', $master->id)
            ->where('recurrence_original_starts_at', '2026-07-13 14:00:00')
            ->count();

        $this->assertSame(1, $count);
    }
}
