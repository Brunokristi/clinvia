<?php

namespace Tests\Unit\Calendar;

use App\Modules\Calendar\Services\EventFrontendMapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class EventFrontendMapperTest extends TestCase
{
    use CreatesCalendarFixtures;
    use RefreshDatabase;

    public function test_availability_rule_legacy_payload_derives_repeat_fields_from_recurrence_rule(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createAvailabilityRuleEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 12:00:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO', 'WE'], 2, ['type' => 'on', 'count' => null, 'until' => '2026-08-01']),
        ]);

        $payload = app(EventFrontendMapper::class)->mapForLegacyPayload($event);

        $this->assertTrue($payload['repeats']);
        $this->assertSame(2, $payload['repeat_every']);
        $this->assertSame('weeks', $payload['repeat_unit']);
        $this->assertSame(['MO', 'WE'], $payload['repeat_weekdays']);
        $this->assertSame('2026-08-01', $payload['repeat_ends_on']);
    }

    public function test_booking_expanded_occurrence_legacy_payload_contains_occurrence_metadata(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'is_recurring' => true,
            'recurrence_rule' => $this->weeklyRecurrence(['MO'], 1, ['type' => 'on', 'count' => null, 'until' => '2026-07-27']),
        ]);

        $occurrence = [
            'event' => $event,
            'root_event' => $event,
            'root_event_id' => $event->id,
            'occurrence_id' => $event->id . '-2026-07-13',
            'occurrence_starts_at' => Carbon::parse('2026-07-13 09:00:00'),
            'occurrence_ends_at' => Carbon::parse('2026-07-13 09:30:00'),
            'is_recurring' => true,
            'is_occurrence' => true,
            'is_override' => false,
        ];

        $payload = app(EventFrontendMapper::class)->mapExpandedOccurrenceForLegacyPayload($occurrence);

        $this->assertSame($event->id, $payload['id']);
        $this->assertSame($event->id, $payload['root_event_id']);
        $this->assertSame('2026-07-13', $payload['occurrence_date']);
        $this->assertSame('booking-' . $occurrence['occurrence_id'], $payload['calendar_event_id']);
        $this->assertTrue($payload['is_occurrence']);
        $this->assertFalse($payload['is_override']);
    }

    public function test_group_event_occurrence_maps_participant_booking_times_to_occurrence_window(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createGroupEvent($fixture, [
            'starts_at' => Carbon::parse('2026-07-06 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 15:00:00'),
        ]);
        $this->addGroupParticipant($event, [
            'participant_name' => 'Mapper Participant',
            'participant_email' => 'mapper.participant@example.com',
        ]);
        $event->refresh()->load(['groupDetail', 'participants', 'services']);

        $payload = app(EventFrontendMapper::class)->mapForLegacyOccurrence($event, Carbon::parse('2026-07-20'));

        $this->assertSame('capacity-window-' . $event->id . '-2026-07-20', $payload['calendar_event_id']);
        $this->assertSame('2026-07-20 14:00:00', Carbon::parse($payload['bookings'][0]['starts_at'])->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 15:00:00', Carbon::parse($payload['bookings'][0]['ends_at'])->format('Y-m-d H:i:s'));
        $this->assertSame('Mapper Participant', $payload['bookings'][0]['patient_name']);
    }
}