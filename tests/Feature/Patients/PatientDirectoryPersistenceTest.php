<?php

namespace Tests\Feature\Patients;

use App\Actions\CreateBookingAction;
use App\Actions\RescheduleBookingAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Patient;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientDirectoryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_booking_persists_patient_to_directory(): void
    {
        $fixture = $this->createFixture();

        app(CreateBookingAction::class)->execute($fixture['branch'], [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => Carbon::parse('2026-07-21 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-21 10:30:00'),
            'patient_name' => 'Jane Doe',
            'patient_email' => 'jane@example.com',
            'patient_phone' => '+421900000111',
            'notify_patient' => false,
        ]);

        $this->assertDatabaseHas('patients', [
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Jane Doe',
            'patient_email' => 'jane@example.com',
            'patient_phone' => '+421900000111',
        ]);
    }

    public function test_rescheduling_booking_updates_existing_patient_entry_by_email(): void
    {
        $fixture = $this->createFixture();

        $booking = app(CreateBookingAction::class)->execute($fixture['branch'], [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => Carbon::parse('2026-07-21 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-21 10:30:00'),
            'patient_name' => 'Jane Doe',
            'patient_email' => 'jane@example.com',
            'patient_phone' => '+421900000111',
            'notify_patient' => false,
        ]);

        app(RescheduleBookingAction::class)->execute($fixture['branch'], $booking, [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => Carbon::parse('2026-07-22 12:00:00'),
            'ends_at' => Carbon::parse('2026-07-22 12:30:00'),
            'patient_name' => 'Jane Doe',
            'patient_email' => 'jane@example.com',
            'patient_phone' => '+421900000222',
        ]);

        $this->assertSame(1, Patient::query()->where('branch_id', $fixture['branch']->id)->count());

        $this->assertDatabaseHas('patients', [
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Jane Doe',
            'patient_email' => 'jane@example.com',
            'patient_phone' => '+421900000222',
        ]);
    }

    private function createFixture(): array
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-patient-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Patient Branch',
            'slug' => 'patient-branch-' . Str::random(6),
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

        return compact('company', 'branch', 'service');
    }
}
