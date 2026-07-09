<?php

namespace Tests\Feature\AppointmentRequests;

use App\Models\AppointmentRequest;
use App\Models\AppointmentRequestAuditLog;
use App\Models\Branch;
use App\Models\BranchPublicSite;
use App\Models\Company;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Models\BookingEventDetail;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestVerificationNotification;
use App\Services\AppointmentRequestVerificationService;
use App\Services\EmailNotificationService;
use App\Services\PatientMatchingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentRequestIdentityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_public_user_submitting_exact_slot_creates_reservation_request(): void
    {
        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();
        $capacityWindow = $this->createGroupCapacityWindow($branch, $service);

        $response = $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), [
            'mode' => 'exact_slot',
            'service_ids' => [$service->id],
            'capacity_window_id' => $capacityWindow->id,
            'patient_name' => 'Anon User',
            'patient_email' => 'anon@example.com',
            'patient_phone' => '0900123123',
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('group_event_participants', 0);
        $this->assertDatabaseHas('appointment_requests', [
            'branch_id' => $branch->id,
            'patient_email' => 'anon@example.com',
        ]);
    }

    public function test_public_booking_form_does_not_expose_reservation_mode_select_labels(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        $response = $this->get(route('public.branch.booking', ['branch' => $branch->slug]));

        $response->assertOk();
        $response->assertDontSee('Spôsob rezervácie');
        $response->assertDontSee('Okamžitá rezervácia');
    }

    public function test_public_request_creates_pending_email_verification_request(): void
    {
        Notification::fake();

        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();

        $response = $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), $this->publicPayload($service));

        $response->assertRedirect();

        $request = AppointmentRequest::query()->latest('id')->first();

        $this->assertNotNull($request);
        $this->assertSame(AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION, $request->status);
        $this->assertNull($request->email_verified_at);
    }

    public function test_verification_email_is_sent_after_public_request_submission(): void
    {
        Notification::fake();

        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();

        $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), $this->publicPayload($service));

        Notification::assertSentOnDemand(RequestVerificationNotification::class);
    }

    public function test_request_stores_verification_token_hash_not_raw_token(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
        ]);

        $token = app(AppointmentRequestVerificationService::class)->issueToken($request);
        $request->refresh();

        $this->assertNotNull($request->verification_token_hash);
        $this->assertNotSame($token, $request->verification_token_hash);
        $this->assertSame(hash('sha256', $token), $request->verification_token_hash);
    }

    public function test_valid_verification_token_changes_status_to_pending_admin_review(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
        ]);

        $token = app(AppointmentRequestVerificationService::class)->issueToken($request);

        $response = $this->get(route('public.branch.booking.request.verify', [
            'branch' => $branch->slug,
            'appointmentRequest' => $request->id,
            'token' => $token,
        ]));

        $response->assertRedirect();

        $request->refresh();

        $this->assertSame(AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW, $request->status);
        $this->assertNotNull($request->email_verified_at);
    }

    public function test_expired_token_does_not_verify_request(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
        ]);

        $token = app(AppointmentRequestVerificationService::class)->issueToken($request);

        $request->forceFill([
            'verification_expires_at' => now()->subMinute(),
        ])->save();

        $response = $this->get(route('public.branch.booking.request.verify', [
            'branch' => $branch->slug,
            'appointmentRequest' => $request->id,
            'token' => $token,
        ]));

        $response->assertRedirect();
        $request->refresh();

        $this->assertNotSame(AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW, $request->status);
        $this->assertNull($request->email_verified_at);
    }

    public function test_branch_is_notified_only_after_verification(): void
    {
        Notification::fake();

        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();

        $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), $this->publicPayload($service));

        $request = AppointmentRequest::query()->latest('id')->firstOrFail();

        $this->assertDatabaseMissing('email_notifications', [
            'notification_type' => 'request.created.branch',
            'notifiable_id' => $request->id,
        ]);

        $token = app(AppointmentRequestVerificationService::class)->issueToken($request);

        $this->get(route('public.branch.booking.request.verify', [
            'branch' => $branch->slug,
            'appointmentRequest' => $request->id,
            'token' => $token,
        ]));

        $this->assertDatabaseHas('email_notifications', [
            'notification_type' => 'request.created.branch',
            'notifiable_id' => $request->id,
        ]);
    }

    public function test_patient_matching_finds_exact_email_match(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Jane Doe',
            'patient_email' => 'Match@Example.com',
            'patient_phone' => '+421900111111',
        ]);

        $request = $this->createAppointmentRequest($branch, [
            'patient_name' => 'Jane Doe',
            'patient_email' => 'match@example.com',
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
        ]);

        $matches = app(PatientMatchingService::class)->findMatchesForRequest($request);

        $this->assertTrue($matches->contains(fn (array $match): bool => $match['confidence'] === 'exact_email'));
    }

    public function test_patient_matching_finds_normalized_phone_match(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'John Doe',
            'patient_email' => 'john@example.com',
            'patient_phone' => '+421900222333',
        ]);

        $request = $this->createAppointmentRequest($branch, [
            'patient_name' => 'John Doe',
            'patient_email' => 'john+req@example.com',
            'patient_phone' => '0900 222 333',
            'normalized_phone' => null,
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
        ]);

        $matches = app(PatientMatchingService::class)->findMatchesForRequest($request);

        $this->assertTrue($matches->contains(fn (array $match): bool => $match['confidence'] === 'exact_phone'));
    }

    public function test_patient_matching_suggests_name_and_birth_date_match(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Jana Novakova',
            'patient_email' => 'jana.old@example.com',
            'patient_phone' => '+421901000000',
            'patient_birth_number' => '9151011234',
        ]);

        $request = $this->createAppointmentRequest($branch, [
            'patient_name' => 'Jana Novakova',
            'first_name' => 'Jana',
            'last_name' => 'Novakova',
            'patient_email' => 'jana.new@example.com',
            'date_of_birth' => '1991-01-01',
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
        ]);

        $matches = app(PatientMatchingService::class)->findMatchesForRequest($request);

        $this->assertTrue($matches->contains(fn (array $match): bool => $match['confidence'] === 'name_and_birth_date'));
    }

    public function test_accepting_request_without_verified_email_is_blocked(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
        ], $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Existing Patient',
            'patient_email' => 'existing@example.com',
        ]);

        $response = $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-21 10:00:00',
            'patient_id' => $patient->id,
        ]);

        $response->assertSessionHasErrors('override_unverified');
    }

    public function test_admin_can_manually_verify_with_reason(): void
    {
        ['branch' => $branch, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
        ]);

        $response = $this->actingAs($admin)->post(route('branches.booking.appointment-requests.manual-verify', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'reason' => 'confirmed by phone',
        ]);

        $response->assertRedirect();

        $request->refresh();
        $this->assertSame(AppointmentRequest::STATUS_MANUALLY_VERIFIED, $request->status);
        $this->assertNotNull($request->manually_verified_at);
        $this->assertSame('confirmed by phone', $request->manual_verification_reason);
    }

    public function test_accepting_request_without_patient_id_or_create_flag_is_blocked(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
        ], $service);

        $response = $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-21 11:00:00',
        ]);

        $response->assertSessionHasErrors('patient_id');
    }

    public function test_confirmed_booking_creation_without_patient_id_fails_for_admin(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $response = $this->actingAs($admin)->post(route('branches.booking.bookings.store', [
            'branch' => $branch,
        ]), [
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'starts_at' => '2026-07-24 10:00:00',
            'ends_at' => '2026-07-24 10:30:00',
            'patient_name' => 'Admin Direct',
            'patient_email' => 'admin.direct@example.com',
            'patient_phone' => '+421900333444',
        ]);

        $response->assertSessionHasErrors('patient_id');
    }

    public function test_admin_can_create_booking_with_patient_id(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Admin Direct',
            'patient_email' => 'admin.direct@example.com',
            'patient_phone' => '+421900333444',
        ]);

        $this->actingAs($admin)->post(route('branches.booking.bookings.store', [
            'branch' => $branch,
        ]), [
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'starts_at' => '2026-07-24 10:00:00',
            'ends_at' => '2026-07-24 10:30:00',
            'patient_id' => $patient->id,
            'patient_name' => 'Admin Direct',
            'patient_email' => 'admin.direct@example.com',
            'patient_phone' => '+421900333444',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_event_details', [
            'patient_id' => $patient->id,
            'booking_source' => 'admin_calendar',
        ]);
    }

    public function test_verified_patient_can_create_direct_public_booking_when_patient_id_is_known(): void
    {
        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();
        $branch->update([
            'booking_settings' => array_merge($branch->booking_settings ?? [], [
                'booking_mode' => 'verified_patients_only',
            ]),
        ]);

        $service->update([
            'public_booking_type' => 'immediate_booking',
        ]);

        $capacityWindow = $this->createGroupCapacityWindow($branch, $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Verified Patient',
            'patient_email' => 'verified@example.com',
            'patient_phone' => '+421900888777',
        ]);

        $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), [
            'mode' => 'exact_slot',
            'service_ids' => [$service->id],
            'capacity_window_id' => $capacityWindow->id,
            'patient_id' => $patient->id,
            'verified_patient_email' => 'verified@example.com',
            'patient_name' => 'Verified Patient',
            'patient_email' => 'verified@example.com',
            'patient_phone' => '0900888777',
            'privacy_consent' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('group_event_participants', [
            'event_id' => $capacityWindow->id,
            'patient_id' => $patient->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_verified_email_with_multiple_patient_matches_cannot_auto_book_without_selection(): void
    {
        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();
        $branch->update([
            'booking_settings' => array_merge($branch->booking_settings ?? [], [
                'booking_mode' => 'verified_patients_only',
            ]),
        ]);

        $service->update([
            'public_booking_type' => 'immediate_booking',
        ]);

        $capacityWindow = $this->createGroupCapacityWindow($branch, $service);

        Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Matched One',
            'patient_email' => 'multi@example.com',
            'patient_phone' => '+421900100100',
        ]);

        Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Matched Two',
            'patient_email' => 'multi@example.com',
            'patient_phone' => '+421900100101',
        ]);

        $response = $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), [
            'mode' => 'exact_slot',
            'service_ids' => [$service->id],
            'capacity_window_id' => $capacityWindow->id,
            'verified_patient_email' => 'multi@example.com',
            'patient_name' => 'Verified Multi',
            'patient_email' => 'multi@example.com',
            'patient_phone' => '0900111222',
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('group_event_participants', 0);
        $this->assertDatabaseHas('appointment_requests', [
            'branch_id' => $branch->id,
            'patient_email' => 'multi@example.com',
        ]);
    }

    public function test_anonymous_user_cannot_force_direct_booking_through_payload(): void
    {
        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();
        $capacityWindow = $this->createGroupCapacityWindow($branch, $service);

        $existingPatient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Unverified Existing',
            'patient_email' => 'existing@example.com',
            'patient_phone' => '+421900000000',
        ]);

        $response = $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), [
            'mode' => 'exact_slot',
            'service_ids' => [$service->id],
            'capacity_window_id' => $capacityWindow->id,
            'patient_id' => $existingPatient->id,
            'verified_patient_email' => 'fake@example.com',
            'patient_name' => 'Anonymous Force',
            'patient_email' => 'anonymous.force@example.com',
            'patient_phone' => '0900999888',
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('group_event_participants', 0);
        $this->assertDatabaseHas('appointment_requests', [
            'branch_id' => $branch->id,
            'patient_email' => 'anonymous.force@example.com',
        ]);
    }

    public function test_verified_patient_falls_back_to_request_when_direct_conditions_fail(): void
    {
        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();
        $branch->update([
            'booking_settings' => array_merge($branch->booking_settings ?? [], [
                'booking_mode' => 'verified_patients_only',
            ]),
        ]);

        $service->update([
            'public_booking_type' => 'immediate_booking',
        ]);

        $capacityWindow = $this->createGroupCapacityWindow($branch, $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Mismatch Patient',
            'patient_email' => 'real@example.com',
            'patient_phone' => '+421901123456',
        ]);

        $response = $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), [
            'mode' => 'exact_slot',
            'service_ids' => [$service->id],
            'capacity_window_id' => $capacityWindow->id,
            'patient_id' => $patient->id,
            'verified_patient_email' => 'different@example.com',
            'patient_name' => 'Mismatch Patient',
            'patient_email' => 'different@example.com',
            'patient_phone' => '0900555666',
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('group_event_participants', 0);
        $this->assertDatabaseHas('appointment_requests', [
            'branch_id' => $branch->id,
            'patient_email' => 'different@example.com',
        ]);
    }

    public function test_admin_booking_mode_controls_public_behavior_not_posted_mode(): void
    {
        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();
        $branch->update([
            'booking_settings' => array_merge($branch->booking_settings ?? [], [
                'booking_mode' => 'requests_only',
            ]),
        ]);

        $service->update([
            'public_booking_type' => 'immediate_booking',
        ]);

        $capacityWindow = $this->createGroupCapacityWindow($branch, $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Known Patient',
            'patient_email' => 'known@example.com',
            'patient_phone' => '+421900222111',
        ]);

        $response = $this->post(route('public.branch.booking.store', ['branch' => $branch->slug]), [
            'mode' => 'exact_slot',
            'service_ids' => [$service->id],
            'capacity_window_id' => $capacityWindow->id,
            'patient_id' => $patient->id,
            'verified_patient_email' => 'known@example.com',
            'patient_name' => 'Known Patient',
            'patient_email' => 'known@example.com',
            'patient_phone' => '0900222111',
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('group_event_participants', 0);
        $this->assertDatabaseHas('appointment_requests', [
            'branch_id' => $branch->id,
            'patient_email' => 'known@example.com',
        ]);
    }

    public function test_booking_page_shows_anonymous_request_flow_information_text(): void
    {
        ['branch' => $branch] = $this->createPublicFixture();

        $response = $this->get(route('public.branch.booking', ['branch' => $branch->slug]));

        $response->assertOk();
        $response->assertSee('Po odoslan\\u00ed po\\u017eiadavky V\\u00e1m pr\\u00edde email na potvrdenie. Po potvrden\\u00ed po\\u017eiadavku skontrolujeme a term\\u00edn V\\u00e1m potvrd\\u00edme.');
    }

    public function test_booking_page_shows_verified_direct_information_text_when_context_is_eligible(): void
    {
        ['branch' => $branch, 'service' => $service] = $this->createPublicFixture();

        $branch->update([
            'booking_settings' => array_merge($branch->booking_settings ?? [], [
                'booking_mode' => 'verified_patients_only',
            ]),
        ]);

        $service->update([
            'public_booking_type' => 'immediate_booking',
        ]);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Eligible Patient',
            'patient_email' => 'eligible@example.com',
            'patient_phone' => '+421900555444',
        ]);

        $response = $this->get(route('public.branch.booking', [
            'branch' => $branch->slug,
            'services' => [$service->id],
            'patient_id' => $patient->id,
            'verified_patient_email' => 'eligible@example.com',
        ]));

        $response->assertOk();
        $response->assertSee('Po potvrden\\u00ed sa term\\u00edn okam\\u017eite zap\\u00ed\\u0161e do kalend\\u00e1ra.');
    }

    public function test_admin_can_link_request_to_existing_patient(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
        ], $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Linked Patient',
            'patient_email' => 'linked@example.com',
            'patient_phone' => '+421900444555',
        ]);

        $response = $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 09:00:00',
            'patient_id' => $patient->id,
        ]);

        $response->assertRedirect();

        $request->refresh();
        $this->assertSame($patient->id, $request->patient_id);
    }

    public function test_admin_can_create_new_patient_from_request(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
            'patient_name' => 'New Person',
            'patient_email' => 'new.person@example.com',
        ], $service);

        $response = $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 10:00:00',
            'force_create_patient' => true,
            'selected_patient' => [
                'patient_name' => 'New Person',
                'patient_email' => 'new.person@example.com',
                'patient_phone' => '+421900555666',
            ],
        ]);

        $response->assertRedirect();

        $request->refresh();
        $this->assertNotNull($request->patient_id);
        $this->assertDatabaseHas('patients', [
            'id' => $request->patient_id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_accepted_request_creates_booking_with_patient_id_and_source_request_id(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
        ], $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Booking Linked',
            'patient_email' => 'booking.linked@example.com',
        ]);

        $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 11:00:00',
            'patient_id' => $patient->id,
        ])->assertRedirect();

        $request->refresh();

        $this->assertSame(AppointmentRequest::STATUS_ACCEPTED, $request->status);
        $this->assertNotNull($request->accepted_booking_id);

        $this->assertDatabaseHas('booking_event_details', [
            'event_id' => $request->accepted_booking_id,
            'patient_id' => $patient->id,
            'source_request_id' => $request->id,
        ]);
    }

    public function test_patient_receives_booking_confirmation_after_acceptance(): void
    {
        Notification::fake();

        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
        ], $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Mail Patient',
            'patient_email' => 'mail.patient@example.com',
        ]);

        $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 12:00:00',
            'patient_id' => $patient->id,
            'notify_patient' => true,
        ])->assertRedirect();

        Notification::assertSentOnDemand(BookingCreatedNotification::class);
    }

    public function test_rejected_request_sends_rejection_email(): void
    {
        Notification::fake();

        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
        ], $service);

        $this->actingAs($admin)->delete(route('branches.booking.appointment-requests.destroy', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'notify_patient' => true,
            'notification_reason' => 'No available slots',
        ])->assertRedirect();

        Notification::assertSentOnDemand(RequestRejectedNotification::class);

        $request->refresh();
        $this->assertSame(AppointmentRequest::STATUS_REJECTED, $request->status);
    }

    public function test_unverified_request_cannot_become_booking_without_admin_override(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
        ], $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Override Patient',
            'patient_email' => 'override@example.com',
        ]);

        $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 13:00:00',
            'patient_id' => $patient->id,
        ])->assertSessionHasErrors('override_unverified');

        $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 13:30:00',
            'patient_id' => $patient->id,
            'override_unverified' => true,
            'manual_verification_reason' => 'confirmed by phone',
        ])->assertRedirect();

        $request->refresh();
        $this->assertSame(AppointmentRequest::STATUS_ACCEPTED, $request->status);
    }

    public function test_duplicate_patient_is_not_auto_created_if_strong_match_exists_without_force_create(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Strong Match',
            'patient_email' => 'strong.match@example.com',
            'patient_phone' => '+421900999888',
        ]);

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
            'patient_name' => 'Strong Match',
            'patient_email' => 'strong.match@example.com',
            'patient_phone' => '+421900999888',
        ], $service);

        $response = $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 14:00:00',
            'force_create_patient' => false,
        ]);

        $response->assertSessionHasErrors('patient_id');
    }

    public function test_existing_patient_email_update_requires_explicit_action_and_creates_audit_log(): void
    {
        ['branch' => $branch, 'service' => $service, 'admin' => $admin] = $this->createPublicFixture();

        $request = $this->createAppointmentRequest($branch, [
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'email_verified_at' => now(),
        ], $service);

        $patient = Patient::query()->create([
            'branch_id' => $branch->id,
            'patient_name' => 'Update Me',
            'patient_email' => 'old.email@example.com',
            'patient_phone' => '+421911000111',
        ]);

        $this->actingAs($admin)->post(route('branches.booking.appointment-requests.convert', [
            'branch' => $branch,
            'appointmentRequest' => $request,
        ]), [
            'starts_at' => '2026-07-22 15:00:00',
            'patient_id' => $patient->id,
            'update_existing_patient_contact' => true,
            'selected_patient' => [
                'patient_email' => 'new.email@example.com',
                'patient_phone' => '+421911000222',
            ],
        ])->assertRedirect();

        $patient->refresh();

        $this->assertSame('new.email@example.com', $patient->patient_email);
        $this->assertDatabaseHas('appointment_request_audit_logs', [
            'appointment_request_id' => $request->id,
            'action' => 'patient_contact_updated',
        ]);
    }

    private function createPublicFixture(): array
    {
        $company = Company::query()->create([
            'legal_name' => 'Identity Company',
            'slug' => 'identity-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Identity Branch',
            'slug' => 'identity-branch-' . Str::random(8),
            'type' => 'clinic',
            'is_active' => true,
            'booking_settings' => [
                'is_enabled' => true,
                'allow_appointment_requests' => true,
                'booking_mode' => 'requests_only',
            ],
            'notification_settings' => [
                'is_enabled' => true,
                'notify_new_appointment_request' => true,
                'notification_emails' => ['branch-notify@example.com'],
            ],
        ]);

        BranchPublicSite::query()->create([
            'branch_id' => $branch->id,
            'is_enabled' => true,
            'template' => 'default',
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
            'public_booking_type' => 'appointment_request',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-' . Str::random(8) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'service', 'admin');
    }

    private function publicPayload(Service $service): array
    {
        return [
            'mode' => 'appointment_request',
            'request_type' => 'general',
            'service_ids' => [$service->id],
            'preferred_date' => '2026-07-21',
            'patient_name' => 'Public Patient',
            'first_name' => 'Public',
            'last_name' => 'Patient',
            'patient_email' => 'public.patient@example.com',
            'patient_phone' => '0900 111 222',
            'patient_note' => 'Need afternoon slot',
            'privacy_consent' => '1',
        ];
    }

    private function createAppointmentRequest(Branch $branch, array $overrides = [], ?Service $service = null): AppointmentRequest
    {
        $service ??= Service::query()->where('branch_id', $branch->id)->firstOrFail();

        $appointmentRequest = AppointmentRequest::query()->create(array_merge([
            'branch_id' => $branch->id,
            'request_type' => 'general',
            'preferred_date' => Carbon::parse('2026-07-21')->toDateString(),
            'preferred_period' => 'morning',
            'total_duration_minutes' => 30,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'patient_name' => 'Test Patient',
            'patient_email' => 'test.patient@example.com',
            'normalized_email' => 'test.patient@example.com',
            'patient_phone' => '+421900123123',
            'normalized_phone' => '+421900123123',
            'patient_note' => 'Please call me',
            'privacy_consent_accepted_at' => now(),
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
        ], $overrides));

        $appointmentRequest->services()->sync([
            $service->id => [
                'duration_minutes_snapshot' => 30,
                'price_snapshot' => null,
            ],
        ]);

        return $appointmentRequest->fresh(['services', 'branch']);
    }

    private function createGroupCapacityWindow(Branch $branch, Service $service): Event
    {
        $event = Event::query()->create([
            'branch_id' => $branch->id,
            'type' => EventType::GroupEvent->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-25 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-25 10:30:00'),
            'timezone' => config('app.timezone', 'Europe/Bratislava'),
            'is_recurring' => false,
        ]);

        $event->groupDetail()->create([
            'service_id' => $service->id,
            'service_name' => $service->name,
            'capacity' => 5,
            'reserved_places' => 0,
            'group_status' => 'active',
        ]);

        $event->services()->sync([
            $service->id => [
                'duration_minutes_snapshot' => 30,
                'price_snapshot' => null,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        return $event->fresh(['groupDetail', 'services']);
    }
}
