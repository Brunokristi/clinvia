<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Events\BranchEventUpdated;
use App\Modules\Calendar\Jobs\SendEventNotificationJob;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventReadAdapterService;
use App\Modules\Calendar\Services\EventFrontendMapper;
use App\Notifications\BookingCreatedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesCalendarFixtures;
use Tests\TestCase;

class UnifiedEventApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCalendarFixtures;

    public function test_can_create_booking_event_and_send_patient_notification(): void
    {
        Notification::fake();
        EventFacade::fake([BranchEventUpdated::class]);

        $fixture = $this->createCalendarFixture();

        $response = $this->actingAs($fixture['user'])->postJson(route('admin.branches.events.store', [
            $fixture['branch']->id,
        ]), [
            'type' => 'booking',
            'status' => 'confirmed',
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-20 09:30:00',
            'services' => [
                [
                    'service_id' => $fixture['service']->id,
                    'duration_minutes_snapshot' => 30,
                    'price_snapshot' => 30,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ],
            'booking_detail' => [
                'patient_name' => 'Test Patient',
                'patient_email' => 'test.patient@example.com',
                'patient_phone' => '+421900123123',
            ],
        ]);

        $response->assertOk();

        $eventId = (int) $response->json('data.id');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'branch_id' => $fixture['branch']->id,
            'type' => 'booking',
        ]);

        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $eventId,
            'patient_name' => 'Test Patient',
        ]);

        EventFacade::assertDispatched(BranchEventUpdated::class);
        Notification::assertSentOnDemandTimes(BookingCreatedNotification::class, 1);
    }

    public function test_event_frontend_mapper_preserves_expected_calendar_shape(): void
    {
        $fixture = $this->createCalendarFixture();
        $event = $this->createBookingEvent($fixture, [
            'starts_at' => '2026-07-20 09:00:00',
            'ends_at' => '2026-07-20 09:30:00',
        ]);

        $mapper = app(EventFrontendMapper::class);

        $mapped = $mapper->mapForCalendar($event);
        $legacy = $mapper->mapForLegacyPayload($event);

        $this->assertSame($event->id, $mapped['id']);
        $this->assertArrayHasKey('title', $mapped);
        $this->assertArrayHasKey('start', $mapped);
        $this->assertArrayHasKey('end', $mapped);
        $this->assertSame('booking', $mapped['type']);
        $this->assertArrayHasKey('extendedProps', $mapped);

        $this->assertSame($event->id, $legacy['id']);
        $this->assertSame('Fixture Patient', $legacy['patient_name']);
        $this->assertArrayHasKey('service_ids', $legacy);
        $this->assertArrayHasKey('calendar_event_id', $legacy);
    }

    public function test_event_read_adapter_expands_recurring_booking_into_future_range(): void
    {
        $fixture = $this->createCalendarFixture();

        $event = $this->createBookingEvent($fixture, [
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
            'metadata' => [
                'recurrence_excluded_dates' => [],
            ],
        ]);

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-20')->startOfDay(),
            Carbon::parse('2026-07-26')->endOfDay(),
        );

        $this->assertCount(1, $payload['calendarBookings']);
        $this->assertSame('2026-07-20', $payload['calendarBookings'][0]['date']);
        $this->assertSame('Fixture Patient', $payload['calendarBookings'][0]['patient_name']);
        $this->assertSame('2026-07-20', $payload['calendarBookings'][0]['occurrence_date']);
    }

    public function test_event_api_index_returns_virtual_occurrences_for_root_recurring_event(): void
    {
        $fixture = $this->createCalendarFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => 'booking',
            'status' => 'confirmed',
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'WE'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-15',
                    'count' => null,
                ],
            ],
        ]);

        $event->bookingDetail()->create([
            'patient_name' => 'Occurrence Patient',
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($fixture['user'])->getJson(route('admin.branches.events.index', [
            $fixture['branch']->id,
            'start' => '2026-07-06',
            'end' => '2026-07-15',
        ]));

        $response->assertOk();
        $response->assertJsonCount(4, 'occurrences');
        $response->assertJsonPath('occurrences.0.root_event_id', $event->id);
        $response->assertJsonPath('occurrences.0.is_occurrence', true);
    }

    public function test_update_this_creates_override_and_leaves_root_unchanged(): void
    {
        $fixture = $this->createCalendarFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => 'booking',
            'status' => 'confirmed',
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
        ]);

        $event->bookingDetail()->create([
            'patient_name' => 'Override Patient',
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($fixture['user'])->patchJson(route('admin.branches.events.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'starts_at' => '2026-07-08 10:00:00',
            'ends_at' => '2026-07-08 10:30:00',
            'recurrence_scope' => 'this',
            'occurrence_starts_at' => '2026-07-13 09:00:00',
            'occurrence_ends_at' => '2026-07-13 09:30:00',
            'booking_detail' => [
                'patient_name' => 'Override Patient Updated',
            ],
        ]);

        $response->assertOk();

        $event->refresh();
        $this->assertSame('2026-07-06 09:00:00', $event->starts_at?->format('Y-m-d H:i:s'));

        $override = Event::query()->where('recurrence_parent_id', $event->id)->first();
        $this->assertNotNull($override);
        $this->assertSame('2026-07-13 09:00:00', $override->recurrence_original_starts_at?->format('Y-m-d H:i:s'));
        $this->assertFalse((bool) $override->is_recurring);
    }

    public function test_update_this_and_following_splits_series(): void
    {
        $fixture = $this->createCalendarFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => 'booking',
            'status' => 'confirmed',
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
        ]);

        $event->bookingDetail()->create([
            'patient_name' => 'Split Patient',
            'booking_status' => 'confirmed',
        ]);

        $response = $this->actingAs($fixture['user'])->patchJson(route('admin.branches.events.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'recurrence_scope' => 'this_and_following',
            'occurrence_starts_at' => '2026-07-20 09:00:00',
            'occurrence_ends_at' => '2026-07-20 09:30:00',
        ]);

        $response->assertOk();

        $event->refresh();
        $this->assertSame('2026-07-13', data_get($event->recurrence_rule, 'ends.until'));

        $newRoot = Event::query()->where('split_from_event_id', $event->id)->first();
        $this->assertNotNull($newRoot);
        $this->assertSame('2026-07-20 10:00:00', $newRoot->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_update_this_rejects_recurrence_rule_change(): void
    {
        $fixture = $this->createCalendarFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => 'booking',
            'status' => 'confirmed',
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 09:30:00',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
        ]);

        $response = $this->actingAs($fixture['user'])->patchJson(route('admin.branches.events.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'recurrence_scope' => 'this',
            'occurrence_starts_at' => '2026-07-13 09:00:00',
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['WE'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recurrence_rule']);
    }

}
