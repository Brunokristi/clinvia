<?php

namespace Tests\Feature\Calendar;

use App\Actions\CreateBookingAction;
use App\Models\Branch;
use App\Models\BranchDisabledDay;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
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

        $this->assertCount(1, $disabledDays);
        $this->assertSame('2026-08-01', $disabledDays->first()->date->toDateString());
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

        $booking = app(CreateBookingAction::class)->execute($branchB, [
            'service_id' => $serviceB->id,
            'service_ids' => [$serviceB->id],
            'starts_at' => '2026-07-15 10:00:00',
            'ends_at' => '2026-07-15 10:30:00',
            'patient_name' => 'Branch Scoped Patient',
            'patient_email' => 'branch-scoped@example.com',
            'patient_phone' => '+421900000111',
            'status' => 'confirmed',
            'notify_patient' => false,
        ]);

        $this->assertNotNull($booking->id);
        $this->assertSame($branchB->id, $booking->branch_id);
    }
}
