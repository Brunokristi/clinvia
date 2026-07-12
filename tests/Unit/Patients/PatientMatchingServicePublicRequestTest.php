<?php

namespace Tests\Unit\Patients;

use App\Enums\ContactChangeStatus;
use App\Enums\PatientMatchStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Patient;
use App\Services\PatientMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientMatchingServicePublicRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_birth_name_email_phone_match_returns_matched_status(): void
    {
        $branch = $this->createBranch();
        $patient = $this->createPatient($branch, 'Ján Novák', 'jan@example.com', '+421900123456', '530101123');

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => 'jan@example.com',
            'patient_phone' => '0900 123 456',
            'patient_birth_number' => '530101/123',
            'is_for_someone_else' => false,
        ]);

        $this->assertSame($patient->id, $result['patient_id']);
        $this->assertSame(PatientMatchStatus::Matched, $result['patient_match_status']);
        $this->assertSame(ContactChangeStatus::None, $result['contact_change_status']);
    }

    public function test_same_email_with_different_casing_is_treated_as_match(): void
    {
        $branch = $this->createBranch();

        $this->createPatient($branch, 'Ján Novák', 'Jan@Example.com', '+421900123456', '530201123');

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => 'jan@example.com',
            'patient_phone' => '+421900123456',
            'patient_birth_number' => '530201123',
            'is_for_someone_else' => false,
        ]);

        $this->assertSame(PatientMatchStatus::Matched, $result['patient_match_status']);
    }

    public function test_same_phone_with_different_formatting_is_treated_as_match(): void
    {
        $branch = $this->createBranch();

        $this->createPatient($branch, 'Ján Novák', 'jan@example.com', '+421900555444', '530301123');

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => 'jan@example.com',
            'patient_phone' => '0900 555 444',
            'patient_birth_number' => '530301123',
            'is_for_someone_else' => false,
        ]);

        $this->assertSame(PatientMatchStatus::Matched, $result['patient_match_status']);
    }

    public function test_matching_birth_number_with_contact_differences_returns_detected_differences(): void
    {
        $branch = $this->createBranch();

        $this->createPatient($branch, 'Ján Novák', 'old@example.com', '+421900111111', '530401123');

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => 'new@example.com',
            'patient_phone' => '+421900222222',
            'patient_birth_number' => '530401123',
            'is_for_someone_else' => false,
        ]);

        $this->assertSame(PatientMatchStatus::MatchedWithDifferences, $result['patient_match_status']);
        $this->assertSame(ContactChangeStatus::Detected, $result['contact_change_status']);
        $this->assertArrayHasKey('email', $result['differences']);
        $this->assertArrayHasKey('phone', $result['differences']);
    }

    public function test_matching_birth_number_with_different_name_returns_identity_conflict(): void
    {
        $branch = $this->createBranch();
        $patient = $this->createPatient($branch, 'Ján Novák', 'jan@example.com', '+421900123456', '530501123');

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Peter Horváth',
            'patient_email' => 'jan@example.com',
            'patient_phone' => '+421900123456',
            'patient_birth_number' => '530501123',
            'is_for_someone_else' => false,
        ]);

        $this->assertNull($result['patient_id']);
        $this->assertSame($patient->id, $result['possible_patient_id']);
        $this->assertSame(PatientMatchStatus::IdentityConflict, $result['patient_match_status']);
    }

    public function test_unknown_birth_number_returns_new_patient_status(): void
    {
        $branch = $this->createBranch();

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => 'jan@example.com',
            'patient_phone' => '+421900123456',
            'patient_birth_number' => '530601123',
            'is_for_someone_else' => false,
        ]);

        $this->assertNull($result['patient_id']);
        $this->assertSame(PatientMatchStatus::NewPatient, $result['patient_match_status']);
    }

    public function test_invalid_birth_number_returns_invalid_status(): void
    {
        $branch = $this->createBranch();

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => 'jan@example.com',
            'patient_phone' => '+421900123456',
            'patient_birth_number' => 'invalid',
            'is_for_someone_else' => false,
        ]);

        $this->assertSame(PatientMatchStatus::InvalidBirthNumber, $result['patient_match_status']);
    }

    public function test_booking_for_someone_else_does_not_compare_requester_contact_to_patient_contact(): void
    {
        $branch = $this->createBranch();

        $patient = $this->createPatient($branch, 'Ján Novák', 'jan@example.com', '+421900123456', '530701123');

        $result = app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => null,
            'patient_phone' => null,
            'patient_birth_number' => '530701123',
            'is_for_someone_else' => true,
            'requester_email' => 'other@example.com',
            'requester_phone' => '+421900999888',
        ]);

        $this->assertSame($patient->id, $result['patient_id']);
        $this->assertSame(PatientMatchStatus::Matched, $result['patient_match_status']);
        $this->assertNull($result['differences']);
    }

    public function test_existing_patient_is_not_overwritten_by_match_evaluation(): void
    {
        $branch = $this->createBranch();

        $patient = $this->createPatient($branch, 'Ján Novák', 'jan@example.com', '+421900123456', '530801123');

        app(PatientMatchingService::class)->matchPublicRequestPayload($branch, [
            'patient_name' => 'Ján Novák',
            'patient_email' => 'new@example.com',
            'patient_phone' => '+421900777666',
            'patient_birth_number' => '530801123',
            'is_for_someone_else' => false,
        ]);

        $patient->refresh();

        $this->assertSame('jan@example.com', $patient->patient_email);
        $this->assertSame('+421900123456', $patient->patient_phone);
    }

    private function createBranch(): Branch
    {
        $company = Company::query()->create([
            'legal_name' => 'Match Company',
            'slug' => 'match-company-' . Str::random(8),
            'is_active' => true,
        ]);

        return Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Match Branch',
            'slug' => 'match-branch-' . Str::random(8),
            'type' => 'clinic',
            'is_active' => true,
        ]);
    }

    private function createPatient(Branch $branch, string $name, string $email, string $phone, string $birthNumber): Patient
    {
        return Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => $name,
            'patient_email' => $email,
            'patient_phone' => $phone,
            'patient_birth_number' => $birthNumber,
        ]);
    }
}
