<?php

namespace Tests\Feature\Calendar;

use App\Actions\CreateBookingAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_migration_supports_dry_run_without_persisting_events(): void
    {
        if (! Schema::hasTable('bookings')) {
            $this->markTestSkipped('Legacy bookings table was removed by hard cutover migration.');
        }

        $fixture = $this->createFixture();

        app(CreateBookingAction::class)->execute($fixture['branch'], [
            'service_id' => $fixture['service']->id,
            'service_ids' => [$fixture['service']->id],
            'starts_at' => '2026-07-20 08:00:00',
            'ends_at' => '2026-07-20 08:30:00',
            'patient_name' => 'Dry Run Patient',
            'patient_email' => 'dry-run@example.com',
            'status' => 'confirmed',
            'notify_patient' => false,
        ]);

        $this->artisan('calendar:migrate-events', [
            '--branch' => $fixture['branch']->id,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('calendar_legacy_event_maps', 0);
    }

    private function createFixture(): array
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'calendar-migrate-admin-' . Str::random(8) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Migration Company',
            'slug' => 'clinvia-migration-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Migration Branch',
            'slug' => 'migration-branch-' . Str::random(8),
            'type' => 'clinic',
            'is_active' => true,
            'booking_settings' => [
                'is_enabled' => true,
            ],
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Migration Service',
            'slug' => 'migration-service-' . Str::random(8),
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
