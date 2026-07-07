<?php

namespace Tests\Feature\Calendar;

use App\Actions\CreateBookingAction;
use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Models\Company;
use App\Models\OpeningHour;
use App\Models\OpeningHourInterval;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Actions\CreateEventAction;
use App\Modules\Calendar\Enums\EventType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DisabledDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_disabled_day_and_it_blocks_bookings_on_that_date(): void
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-clinic',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('branches.booking.disabled-days.store', $branch), [
                'date' => '2026-07-15',
                'title' => 'Holiday',
                'type' => 'holiday',
                'reason' => 'Clinic closed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branch_disabled_days', [
            'branch_id' => $branch->id,
            'date' => '2026-07-15 00:00:00',
            'title' => 'Holiday',
        ]);

        $this->expectException(ValidationException::class);

        app(CreateBookingAction::class)->execute($branch, [
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'starts_at' => '2026-07-15 10:00:00',
            'ends_at' => '2026-07-15 10:30:00',
            'patient_name' => 'John Doe',
            'patient_email' => 'john@example.com',
            'patient_phone' => '+421900000000',
            'status' => 'confirmed',
            'notify_patient' => false,
        ]);
    }

    public function test_disabled_day_service_returns_disabled_dates_for_a_range(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-clinic-2',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-2',
            'type' => 'clinic',
            'is_active' => true,
        ]);

        BranchDisabledDay::query()->create([
            'branch_id' => $branch->id,
            'created_by' => null,
            'date' => '2026-08-01',
            'title' => 'Public Holiday',
            'type' => 'holiday',
            'reason' => null,
        ]);

        $service = app(\App\Services\DisabledDayService::class);

        $disabledDays = $service->getDisabledDaysForRange(
            $branch,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-08-31'),
        );

        $dates = $disabledDays
            ->pluck('date')
            ->map(fn ($date) => (string) $date)
            ->all();

        $this->assertContains('2026-08-01', $dates);
    }

    public function test_holiday_is_closed_by_default_and_can_be_opened_with_override(): void
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'holiday-open@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-clinic-holiday-open',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-holiday-open',
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = app(\App\Services\DisabledDayService::class);

        $this->assertTrue($service->isDisabled($branch, '2026-01-01'));

        $this->actingAs($user)
            ->post(route('branches.booking.disabled-days.store', $branch), [
                'date' => '2026-01-01',
                'title' => 'Otvoreny sviatok',
                'type' => 'holiday_open',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branch_disabled_days', [
            'branch_id' => $branch->id,
            'date' => '2026-01-01 00:00:00',
            'type' => 'holiday_open',
        ]);

        $this->assertFalse($service->isDisabled($branch, '2026-01-01'));

        $disabledDays = $service->getDisabledDaysForRange(
            $branch,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-02'),
        );

        $dates = $disabledDays
            ->pluck('date')
            ->map(fn ($date) => (string) $date)
            ->all();

        $this->assertNotContains('2026-01-01', $dates);
    }

    public function test_unified_booking_event_creation_is_blocked_on_holiday_by_default(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-clinic-unified-holiday-block',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-unified-holiday-block',
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Consultation',
            'slug' => 'consultation-unified-holiday-block',
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateEventAction::class)->execute($branch, [
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => '2026-01-01 09:00:00',
            'ends_at' => '2026-01-01 09:30:00',
            'services' => [
                [
                    'service_id' => $service->id,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ],
            'booking_detail' => [
                'patient_name' => 'Unified Holiday Block',
                'patient_email' => 'holiday-block@example.com',
            ],
        ]);
    }

    public function test_unified_booking_event_creation_is_blocked_when_day_is_closed_by_opening_hours(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-clinic-opening-hours-block',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-opening-hours-block',
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $monday = OpeningHour::query()->create([
            'branch_id' => $branch->id,
            'day_of_week' => 1,
            'is_closed' => false,
        ]);

        OpeningHourInterval::query()->create([
            'opening_hour_id' => $monday->id,
            'opens_at' => '09:00:00',
            'closes_at' => '12:00:00',
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Consultation',
            'slug' => 'consultation-opening-hours-block',
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        // Tuesday has no opening hours configured -> day is closed.
        app(CreateEventAction::class)->execute($branch, [
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => '2026-01-06 10:00:00',
            'ends_at' => '2026-01-06 10:30:00',
            'services' => [
                [
                    'service_id' => $service->id,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ],
            'booking_detail' => [
                'patient_name' => 'Opening Hours Block',
                'patient_email' => 'opening-hours-block@example.com',
            ],
        ]);
    }

    public function test_unified_availability_rule_creation_is_blocked_on_manually_disabled_day(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-clinic-rule-disabled-day',
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'slug' => 'main-branch-rule-disabled-day',
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Consultation',
            'slug' => 'consultation-rule-disabled-day',
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        BranchDisabledDay::query()->create([
            'branch_id' => $branch->id,
            'created_by' => null,
            'date' => '2026-07-15',
            'title' => 'Closed Day',
            'type' => 'closed',
            'reason' => null,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateEventAction::class)->execute($branch, [
            'type' => EventType::AvailabilityRule->value,
            'status' => 'confirmed',
            'starts_at' => '2026-07-15 10:00:00',
            'ends_at' => '2026-07-15 10:15:00',
            'services' => [
                [
                    'service_id' => $service->id,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ],
            'availability_rule_detail' => [
                'slot_interval_minutes' => 15,
            ],
        ]);
    }

    public function test_disabled_day_is_branch_specific(): void
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
            'slug' => 'clinvia-disabled-day-branch-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branchA = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Branch A',
            'slug' => 'branch-a-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $branchB = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Branch B',
            'slug' => 'branch-b-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $serviceB = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branchB->id,
            'name' => 'Consultation B',
            'slug' => 'consultation-b-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('branches.booking.disabled-days.store', $branchA), [
                'date' => '2026-07-15',
                'title' => 'Holiday',
                'type' => 'holiday',
                'reason' => 'Clinic closed.',
            ])
            ->assertRedirect();

        $event = app(CreateEventAction::class)->execute($branchB, [
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => '2026-07-15 10:00:00',
            'ends_at' => '2026-07-15 10:30:00',
            'services' => [
                [
                    'service_id' => $serviceB->id,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ],
            'booking_detail' => [
                'patient_name' => 'Branch Scoped Patient',
                'patient_email' => 'branch-scoped@example.com',
                'patient_phone' => '+421900000111',
            ],
        ]);

        $this->assertNotNull($event->id);
        $this->assertSame($branchB->id, $event->branch_id);
    }
}
