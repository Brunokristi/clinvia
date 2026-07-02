<?php

namespace Tests\Feature\Patients;

use App\Models\Branch;
use App\Models\Company;
use App\Services\PatientDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientDirectoryBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_autocomplete_returns_only_patients_for_requested_branch(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-company-' . Str::random(8),
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

        $service = app(PatientDirectoryService::class);

        $service->savePatient($branchA, 'Alice A', 'alice.a@example.com', '+421900000001');
        $service->savePatient($branchB, 'Bob B', 'bob.b@example.com', '+421900000002');

        $patientsForA = $service->getBranchPatientsForAutocomplete($branchA)->pluck('patient_name')->all();
        $patientsForB = $service->getBranchPatientsForAutocomplete($branchB)->pluck('patient_name')->all();

        $this->assertSame(['Alice A'], $patientsForA);
        $this->assertSame(['Bob B'], $patientsForB);
    }
}
