<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Models\Booking;
use App\Models\CapacityWindow;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CapacityWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_event_capacity_blocks_overbooking(): void
    {
        $fixture = $this->createFixture();

        $window = CapacityWindow::query()->create([
            'branch_id' => $fixture['branch']->id,
            'service_id' => $fixture['service']->id,
            'series_uuid' => null,
            'starts_at' => '2026-07-21 10:00:00',
            'ends_at' => '2026-07-21 11:00:00',
            'capacity' => 1,
            'status' => 'active',
            'admin_note' => null,
        ]);

        $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.bookings.store', [
            $fixture['branch']->id,
            $window->id,
        ]), [
            'patient_name' => 'First Patient',
            'patient_email' => 'first@example.com',
            'patient_phone' => '+421900000221',
            'notify_patient' => false,
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.bookings.store', [
            $fixture['branch']->id,
            $window->id,
        ]), [
            'patient_name' => 'Second Patient',
            'patient_email' => 'second@example.com',
            'patient_phone' => '+421900000222',
            'notify_patient' => false,
        ])->assertSessionHasErrors('capacity_window_id');

        $window->refresh();

        $this->assertSame(1, $window->activeBookings()->count());
    }

    public function test_group_event_duplicate_copy_does_not_copy_participants(): void
    {
        $fixture = $this->createFixture();

        $originalWindow = CapacityWindow::query()->create([
            'branch_id' => $fixture['branch']->id,
            'service_id' => $fixture['service']->id,
            'series_uuid' => null,
            'starts_at' => '2026-07-23 10:00:00',
            'ends_at' => '2026-07-23 11:00:00',
            'capacity' => 5,
            'status' => 'active',
            'admin_note' => null,
        ]);

        Booking::query()->create([
            'branch_id' => $fixture['branch']->id,
            'service_id' => $fixture['service']->id,
            'capacity_window_id' => $originalWindow->id,
            'starts_at' => '2026-07-23 10:00:00',
            'ends_at' => '2026-07-23 11:00:00',
            'patient_name' => 'Existing Participant',
            'patient_email' => 'existing@example.com',
            'patient_phone' => '+421900000777',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-07-24 10:00:00',
            'ends_at' => '2026-07-24 11:00:00',
            'capacity' => 5,
            'public_booking_type' => 'immediate_booking',
            'repeats' => false,
        ]);

        $response->assertSessionHasNoErrors();

        $duplicatedWindow = CapacityWindow::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('id', '!=', $originalWindow->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1, $originalWindow->activeBookings()->count());
        $this->assertSame(0, $duplicatedWindow->activeBookings()->count());
    }

    public function test_patient_can_be_added_to_one_recurring_occurrence_without_affecting_series(): void
    {
        $fixture = $this->createFixture();
        $event = $this->createRecurringGroupEvent($fixture, [
            'starts_at' => '2026-08-03 10:00:00',
            'ends_at' => '2026-08-03 11:00:00',
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-09-01',
                    'count' => null,
                ],
            ],
        ]);

        $response = $this->actingAs($fixture['user'])->put(route('branches.booking.capacity-windows.update', [
            $fixture['branch']->id,
            $event->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'capacity' => 5,
            'public_booking_type' => 'immediate_booking',
            'update_scope' => 'occurrence',
            'starts_at' => '2026-08-03 10:00:00',
            'ends_at' => '2026-08-03 11:00:00',
            'sync_patients' => true,
            'group_patients' => [
                [
                    'patient_name' => 'Recurring Occurrence Patient',
                    'patient_email' => 'patient@example.com',
                    'patient_phone' => '+421900000333',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $override = Event::query()
            ->where('recurrence_parent_id', $event->id)
            ->where('recurrence_original_starts_at', '2026-08-03 10:00:00')
            ->first();

        $this->assertNotNull($override);
        $this->assertSame(1, $override->participants()->where('status', 'confirmed')->count());
        $this->assertSame(0, $event->fresh()->participants()->where('status', 'confirmed')->count());
    }

    public function test_recurring_group_event_weekdays_creates_all_selected_weekdays(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-08-03 10:00:00', // Monday
            'ends_at' => '2026-08-03 11:00:00',
            'capacity' => 5,
            'public_booking_type' => 'immediate_booking',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'WE', 'FR'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-08-07',
                    'count' => null,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $master = Event::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('type', EventType::GroupEvent)
            ->whereNull('recurrence_parent_id')
            ->latest('id')
            ->firstOrFail();

        $dates = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-08-03')->startOfDay(),
            Carbon::parse('2026-08-07')->endOfDay(),
            [EventType::GroupEvent],
        )
            ->where('root_event_id', $master->id)
            ->pluck('occurrence_starts_at')
            ->map(fn (Carbon $startsAt): string => $startsAt->toDateString())
            ->all();

        $this->assertSame(['2026-08-03', '2026-08-05', '2026-08-07'], $dates);
    }

    public function test_recurring_group_event_skips_disabled_days_and_creates_remaining_series(): void
    {
        $fixture = $this->createFixture();

        BranchDisabledDay::query()->create([
            'branch_id' => $fixture['branch']->id,
            'created_by' => null,
            'date' => '2026-08-05', // Wednesday
            'title' => 'Closed day',
            'type' => 'holiday',
            'reason' => null,
        ]);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-08-03 10:00:00', // Monday
            'ends_at' => '2026-08-03 11:00:00',
            'capacity' => 5,
            'public_booking_type' => 'immediate_booking',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'WE', 'FR'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-08-07',
                    'count' => null,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $master = Event::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('type', EventType::GroupEvent)
            ->whereNull('recurrence_parent_id')
            ->latest('id')
            ->firstOrFail();

        $dates = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-08-03')->startOfDay(),
            Carbon::parse('2026-08-07')->endOfDay(),
            [EventType::GroupEvent],
        )
            ->where('root_event_id', $master->id)
            ->pluck('occurrence_starts_at')
            ->map(fn (Carbon $startsAt): string => $startsAt->toDateString())
            ->all();

        $this->assertSame(['2026-08-03', '2026-08-07'], $dates);
    }

    public function test_recurring_group_event_without_end_is_auto_capped(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-08-03 10:00:00',
            'ends_at' => '2026-08-03 11:00:00',
            'capacity' => 5,
            'public_booking_type' => 'immediate_booking',
            'recurrence' => [
                'frequency' => 'daily',
                'interval' => 1,
                'ends' => [
                    'type' => 'never',
                    'count' => null,
                    'until' => null,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $master = Event::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('type', EventType::GroupEvent)
            ->whereNull('recurrence_parent_id')
            ->latest('id')
            ->firstOrFail();

        $count = app(RecurrenceExpansionService::class)->forBranch(
            $fixture['branch'],
            Carbon::parse('2026-08-03')->startOfDay(),
            Carbon::parse('2027-08-08')->endOfDay(),
            [EventType::GroupEvent],
        )
            ->where('root_event_id', $master->id)
            ->count();

        $this->assertGreaterThan(300, $count);
        $this->assertSame(1, Event::query()->where('branch_id', $fixture['branch']->id)->where('type', EventType::GroupEvent)->count());
    }

    private function createRecurringGroupEvent(array $fixture, array $overrides = []): Event
    {
        $event = Event::query()->create(array_replace([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::GroupEvent->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-08-03 10:00:00'),
            'ends_at' => Carbon::parse('2026-08-03 11:00:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'until' => '2026-09-01',
                    'count' => null,
                ],
            ],
            'metadata' => [
                'series_uuid' => (string) Str::uuid(),
            ],
        ], $overrides));

        $event->groupDetail()->create([
            'service_id' => $fixture['service']->id,
            'service_name' => $fixture['service']->name,
            'capacity' => 5,
            'reserved_places' => 0,
            'group_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $fixture['service']->id => [
                'duration_minutes_snapshot' => $fixture['service']->duration_minutes,
                'price_snapshot' => $fixture['service']->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        return $event->fresh(['groupDetail', 'services', 'participants']);
    }

    private function createFixture(): array
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-' . Str::random(6) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-capacity-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Capacity Branch',
            'slug' => 'capacity-branch-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Group Therapy',
            'slug' => 'group-therapy-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 60,
            'capacity' => 10,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'group',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        return compact('user', 'company', 'branch', 'service');
    }
}
