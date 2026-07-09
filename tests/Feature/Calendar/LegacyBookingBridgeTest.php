<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventReadAdapterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyBookingBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_booking_route_stores_unified_booking_event(): void
    {
        $fixture = $this->createFixture();
        $patient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Legacy Bridge Patient',
            'patient_email' => 'legacy.bridge@example.com',
            'patient_phone' => '+421900001111',
        ]);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'patient_id' => $patient->id,
            'patient_name' => 'Legacy Bridge Patient',
            'patient_email' => 'legacy.bridge@example.com',
            'patient_phone' => '+421900001111',
        ]);

        $response->assertRedirect();

        $event = Event::query()->first();

        $this->assertNotNull($event);
        $this->assertSame('booking', $event->type->value);
        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $event->id,
            'patient_id' => $patient->id,
            'patient_name' => 'Legacy Bridge Patient',
        ]);
    }

    public function test_legacy_booking_reschedule_route_updates_booking_detail_payload(): void
    {
        $fixture = $this->createFixture();
        $patient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Old Name',
            'patient_email' => 'old@example.com',
            'patient_phone' => '+421900001111',
        ]);

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'patient_id' => $patient->id,
            'patient_name' => 'Old Name',
            'patient_email' => 'old@example.com',
            'patient_phone' => '+421900001111',
            'patient_birth_number' => '123456/7890',
        ])->assertRedirect();

        $event = Event::query()->firstOrFail();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-21 11:00:00',
            'ends_at' => '2026-07-21 11:45:00',
            'patient_name' => 'New Name',
            'patient_email' => 'new@example.com',
            'patient_phone' => '+421900009999',
            'patient_birth_number' => '999999/0000',
            'patient_note' => 'Updated public note',
            'admin_note' => 'Updated admin note',
            'reschedule_scope' => 'series',
            'date' => '2026-07-20',
        ])->assertRedirect();

        $event->refresh();

        $this->assertSame('2026-07-21 11:00:00', $event->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-21 11:30:00', $event->ends_at?->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $event->id,
            'patient_id' => $patient->id,
            'patient_name' => 'New Name',
            'patient_email' => 'new@example.com',
            'patient_phone' => '+421900009999',
            'patient_birth_number' => '999999/0000',
            'public_notes' => 'Updated public note',
            'internal_notes' => 'Updated admin note',
        ]);
    }

    public function test_legacy_booking_store_route_fails_without_patient_id(): void
    {
        $fixture = $this->createFixture();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-22 10:00:00',
            'ends_at' => '2026-07-22 10:30:00',
            'patient_name' => 'Missing Id Patient',
            'patient_email' => 'missing.id@example.com',
            'patient_phone' => '+421900111222',
        ])->assertSessionHasErrors('patient_id');
    }

    public function test_legacy_booking_store_route_rejects_patient_from_another_branch(): void
    {
        $fixture = $this->createFixture();

        $otherCompany = Company::query()->create([
            'legal_name' => 'Other Company',
            'slug' => 'other-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $otherBranch = Branch::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch-' . Str::random(8),
            'type' => 'clinic',
            'is_active' => true,
            'booking_settings' => [
                'is_enabled' => true,
                'calendar_addon_enabled' => true,
                'booking_addon_enabled' => true,
            ],
        ]);

        $foreignPatient = Patient::query()->create([
            'branch_id' => $otherBranch->id,
            'patient_name' => 'Foreign Patient',
            'patient_email' => 'foreign.patient@example.com',
            'patient_phone' => '+421900666555',
        ]);

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-22 12:00:00',
            'ends_at' => '2026-07-22 12:30:00',
            'patient_id' => $foreignPatient->id,
            'patient_name' => 'Foreign Patient',
            'patient_email' => 'foreign.patient@example.com',
            'patient_phone' => '+421900666555',
        ])->assertSessionHasErrors('patient_id');
    }

    public function test_legacy_booking_update_route_recalculates_end_time_from_services(): void
    {
        $fixture = $this->createFixture();

        $patient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Bridge Patient',
            'patient_email' => 'bridge.patient@example.com',
            'patient_phone' => '+421900123456',
        ]);

        $additionalService = Service::query()->create([
            'company_id' => $fixture['company']->id,
            'branch_id' => $fixture['branch']->id,
            'name' => 'Long Service',
            'slug' => 'long-service-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 45,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 10:30:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
            'metadata' => [
                'series_uuid' => (string) Str::uuid(),
            ],
        ]);

        $event->bookingDetail()->create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->patient_name,
            'patient_email' => $patient->patient_email,
            'patient_phone' => $patient->patient_phone,
            'booking_source' => 'admin_calendar',
            'booking_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => $fixture['service']->duration_minutes,
                'price_snapshot' => $fixture['service']->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $this->actingAs($fixture['user'])->put(route('branches.booking.bookings.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_ids' => [$fixture['service']->id, $additionalService->id],
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 12:30:00',
            'patient_name' => 'Bridge Patient',
            'update_scope' => 'series',
        ])->assertSessionHasNoErrors();

        $event->refresh();

        $this->assertSame('2026-07-20 12:00:00', $event->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 13:15:00', $event->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame([
            $fixture['service']->id,
            $additionalService->id,
        ], $event->services()->orderBy('event_service.sort_order')->pluck('services.id')->all());
    }

    public function test_legacy_booking_update_route_rejects_patient_id_mutation(): void
    {
        $fixture = $this->createFixture();

        $patient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Immutable Patient',
            'patient_email' => 'immutable.patient@example.com',
            'patient_phone' => '+421900987654',
        ]);

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 10:30:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
        ]);

        $event->bookingDetail()->create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->patient_name,
            'booking_source' => 'admin_calendar',
            'booking_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => $fixture['service']->duration_minutes,
                'price_snapshot' => $fixture['service']->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $this->actingAs($fixture['user'])->put(route('branches.booking.bookings.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 11:00:00',
            'patient_id' => 999999,
            'update_scope' => 'series',
        ])->assertSessionHasErrors([
            'patient_id' => 'Pacienta existujúcej rezervácie nie je možné zmeniť.',
        ]);

        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $event->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_legacy_booking_update_route_rejects_event_type_mutation_payload(): void
    {
        $fixture = $this->createFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 10:30:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => $fixture['service']->duration_minutes,
                'price_snapshot' => $fixture['service']->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $this->actingAs($fixture['user'])->put(route('branches.booking.bookings.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 11:00:00',
            'event_type' => 'group_event',
            'update_scope' => 'series',
        ])->assertSessionHasErrors('event_type');
    }

    public function test_legacy_booking_update_without_patient_payload_preserves_patient_id(): void
    {
        $fixture = $this->createFixture();

        $patient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Stable Patient',
            'patient_email' => 'stable.patient@example.com',
            'patient_phone' => '+421900444333',
        ]);

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 10:30:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
        ]);

        $event->bookingDetail()->create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->patient_name,
            'booking_source' => 'admin_calendar',
            'booking_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => $fixture['service']->duration_minutes,
                'price_snapshot' => $fixture['service']->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $this->actingAs($fixture['user'])->put(route('branches.booking.bookings.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 12:00:00',
            'patient_name' => 'Stable Patient Updated Name',
            'update_scope' => 'series',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $event->id,
            'patient_id' => $patient->id,
            'patient_name' => 'Stable Patient Updated Name',
        ]);
    }

    public function test_legacy_booking_reschedule_rejects_staff_id_payload(): void
    {
        $fixture = $this->createFixture();

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 10:30:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => $fixture['service']->duration_minutes,
                'price_snapshot' => $fixture['service']->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-07-21 11:00:00',
            'staff_id' => 12,
            'reschedule_scope' => 'series',
        ])->assertSessionHasErrors('staff_id');
    }

    public function test_legacy_booking_reschedule_rejects_patient_id_mutation_with_exact_message(): void
    {
        $fixture = $this->createFixture();

        $patient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Immutable Reschedule Patient',
            'patient_email' => 'immutable.reschedule.patient@example.com',
            'patient_phone' => '+421900222111',
        ]);

        $event = Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 10:30:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
        ]);

        $event->bookingDetail()->create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->patient_name,
            'booking_source' => 'admin_calendar',
            'booking_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => $fixture['service']->duration_minutes,
                'price_snapshot' => $fixture['service']->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-07-20 13:00:00',
            'patient_id' => 123456,
            'reschedule_scope' => 'series',
        ])->assertSessionHasErrors([
            'patient_id' => 'Pacienta existujúcej rezervácie nie je možné zmeniť.',
        ]);

        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $event->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_cancelled_booking_is_excluded_from_legacy_calendar_payload(): void
    {
        $fixture = $this->createFixture();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'patient_name' => 'Delete Me',
            'patient_email' => 'deleteme@example.com',
        ])->assertRedirect();

        $event = Event::query()->firstOrFail();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.cancel', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'delete_scope' => 'series',
            'date' => '2026-07-20',
        ])->assertRedirect();

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-20')->startOfDay(),
            Carbon::parse('2026-07-20')->endOfDay(),
        );

        $this->assertCount(0, $payload['calendarBookings']);
    }

    public function test_removing_recurrence_stops_future_occurrences_from_selected_booking_date(): void
    {
        $fixture = $this->createFixture();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-06 10:00:00',
            'ends_at' => '2026-07-06 10:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'recurring@example.com',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
        ])->assertRedirect();

        $seriesEvent = Event::query()->firstOrFail();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $seriesEvent->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'recurring@example.com',
            'recurrence' => null,
            'reschedule_scope' => 'from_date',
            'date' => '2026-07-20',
        ])->assertRedirect();

        $seriesEvent->refresh();

        $this->assertSame('on', data_get($seriesEvent->recurrence_rule, 'ends.type'));
        $this->assertSame('2026-07-13', data_get($seriesEvent->recurrence_rule, 'ends.until'));

        $newEvent = Event::query()
            ->where('id', '!=', $seriesEvent->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse((bool) $newEvent->is_recurring);
        $this->assertNull($newEvent->recurrence_rule);
        $this->assertSame('2026-07-20 10:00:00', $newEvent->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 10:30:00', $newEvent->ends_at?->format('Y-m-d H:i:s'));
    }

    public function test_repeated_this_and_following_reschedule_with_fresh_payload_does_not_duplicate_calendar_bookings(): void
    {
        $fixture = $this->createFixture();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-06 10:00:00',
            'ends_at' => '2026-07-06 10:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'recurring@example.com',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
        ])->assertRedirect();

        $seriesEvent = Event::query()->firstOrFail();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $seriesEvent->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 12:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'recurring@example.com',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
            'reschedule_scope' => 'from_date',
            'date' => '2026-07-20',
        ])->assertSessionHasNoErrors();

        $firstPayload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $freshOccurrence = collect($firstPayload['calendarBookings'])
            ->first(fn (array $booking): bool => ($booking['date'] ?? null) === '2026-07-27');

        $this->assertNotNull($freshOccurrence);

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.reschedule', [
            $fixture['branch']->id,
            $freshOccurrence['id'],
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-27 13:00:00',
            'ends_at' => '2026-07-27 13:30:00',
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'recurring@example.com',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-07-27',
                    'count' => null,
                ],
            ],
            'reschedule_scope' => 'from_date',
            'date' => '2026-07-27',
        ])->assertSessionHasNoErrors();

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $calendarBookings = collect($payload['calendarBookings']);

        $this->assertCount(4, $calendarBookings);
        $this->assertSame(
            ['2026-07-06', '2026-07-13', '2026-07-20', '2026-07-27'],
            $calendarBookings->pluck('date')->values()->all(),
        );
        $this->assertSame(
            ['2026-07-06T10:00:00', '2026-07-13T10:00:00', '2026-07-20T12:00:00', '2026-07-27T13:00:00'],
            $calendarBookings->pluck('starts_at')->values()->all(),
        );
        $this->assertCount(
            $calendarBookings->count(),
            $calendarBookings->pluck('calendar_event_id')->unique()->values()->all(),
        );
        $this->assertSame(
            [1, 1, 1, 1],
            $calendarBookings->groupBy('date')->map->count()->values()->all(),
        );
    }

    public function test_legacy_calendar_payload_preserves_availability_rule_repeat_fields(): void
    {
        $fixture = $this->createFixture();

        Event::query()->create([
            'branch_id' => $fixture['branch']->id,
            'type' => 'availability_rule',
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-06 08:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 12:00:00'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 2,
                'weekdays' => ['MO', 'WE'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-08-31',
                ],
            ],
            'is_recurring' => true,
            'metadata' => [
                'recurrence_excluded_dates' => ['2026-07-20'],
            ],
        ]);

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        );

        $rule = $payload['availabilityRules'][0] ?? null;

        $this->assertNotNull($rule);
        $this->assertTrue((bool) ($rule['repeats'] ?? false));
        $this->assertSame(2, $rule['repeat_every'] ?? null);
        $this->assertSame('weeks', $rule['repeat_unit'] ?? null);
        $this->assertSame(['MO', 'WE'], $rule['repeat_weekdays'] ?? null);
        $this->assertSame('2026-08-31', $rule['repeat_ends_on'] ?? null);
        $this->assertSame(['2026-07-20'], $rule['excluded_dates'] ?? null);
    }

    private function createFixture(): array
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'calendar-legacy-bridge-' . Str::random(8) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Bridge Company',
            'slug' => 'clinvia-bridge-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Bridge Branch',
            'slug' => 'bridge-branch-' . Str::random(8),
            'type' => 'clinic',
            'is_active' => true,
            'booking_settings' => [
                'is_enabled' => true,
                'calendar_addon_enabled' => true,
                'booking_addon_enabled' => true,
            ],
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Bridge Service',
            'slug' => 'bridge-service-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        return compact('user', 'company', 'branch', 'service');
    }
}
