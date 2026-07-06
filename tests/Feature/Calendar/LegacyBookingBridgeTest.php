<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
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

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
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
            'patient_name' => 'Legacy Bridge Patient',
        ]);
    }

    public function test_legacy_booking_reschedule_route_updates_booking_detail_payload(): void
    {
        $fixture = $this->createFixture();

        $this->actingAs($fixture['user'])->post(route('branches.booking.bookings.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
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
        $this->assertSame('2026-07-21 11:45:00', $event->ends_at?->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $event->id,
            'patient_name' => 'New Name',
            'patient_email' => 'new@example.com',
            'patient_phone' => '+421900009999',
            'patient_birth_number' => '999999/0000',
            'public_notes' => 'Updated public note',
            'internal_notes' => 'Updated admin note',
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
