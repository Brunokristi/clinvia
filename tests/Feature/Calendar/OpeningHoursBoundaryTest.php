<?php

namespace Tests\Feature\Calendar;

use App\Actions\CreateBookingAction;
use App\Actions\RescheduleBookingAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\OpeningHour;
use App\Models\OpeningHourInterval;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpeningHoursBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_creation_outside_opening_hours_is_rejected(): void
    {
        $fixture = $this->createFixture();

        $this->expectException(ValidationException::class);

        app(CreateBookingAction::class)->execute($fixture['branch'], [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => Carbon::parse('2026-07-06 06:30:00'),
            'ends_at' => Carbon::parse('2026-07-06 07:30:00'),
            'patient_name' => 'Before Open',
            'patient_email' => 'before@example.com',
            'patient_phone' => '+421900000010',
            'notify_patient' => false,
        ]);
    }

    public function test_booking_reschedule_outside_opening_hours_is_rejected(): void
    {
        $fixture = $this->createFixture();

        $booking = app(CreateBookingAction::class)->execute($fixture['branch'], [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => Carbon::parse('2026-07-06 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-06 09:30:00'),
            'patient_name' => 'Allowed Patient',
            'patient_email' => 'allowed@example.com',
            'patient_phone' => '+421900000011',
            'notify_patient' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(RescheduleBookingAction::class)->execute($fixture['branch'], $booking, [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => Carbon::parse('2026-07-06 06:30:00'),
            'ends_at' => Carbon::parse('2026-07-06 07:30:00'),
            'patient_name' => 'Allowed Patient',
            'patient_email' => 'allowed@example.com',
            'patient_phone' => '+421900000011',
        ]);
    }

    public function test_capacity_window_creation_outside_opening_hours_is_rejected(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.capacity-windows.store', [
            $fixture['branch']->id,
        ]), [
            'service_id' => $fixture['service']->id,
            'starts_at' => '2026-07-06 06:30:00',
            'ends_at' => '2026-07-06 07:30:00',
            'capacity' => 5,
            'public_booking_type' => 'immediate_booking',
        ]);

        $response->assertSessionHasErrors('starts_at');
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
            'slug' => 'clinvia-opening-hours-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Opening Hours Branch',
            'slug' => 'opening-hours-branch-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Consultation',
            'slug' => 'consultation-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $openingHour = OpeningHour::query()->create([
            'branch_id' => $branch->id,
            'day_of_week' => 1,
            'is_closed' => false,
            'note' => null,
            'sort_order' => 1,
        ]);

        OpeningHourInterval::query()->create([
            'opening_hour_id' => $openingHour->id,
            'opens_at' => '07:00:00',
            'closes_at' => '20:00:00',
            'sort_order' => 1,
        ]);

        return compact('user', 'company', 'branch', 'service');
    }
}
