<?php

namespace Tests\Feature\Notifications;

use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Service;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\RequestCreatedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Services\EmailNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_created_is_sent_once_for_patient_and_branch_is_throttled(): void
    {
        Notification::fake();

        ['branch' => $branch, 'service' => $service] = $this->createFixture();
        $emailNotificationService = app(EmailNotificationService::class);

        $requestOne = $this->createAppointmentRequest($branch, $service, 'first@example.com');
        $requestTwo = $this->createAppointmentRequest($branch, $service, 'second@example.com');

        $emailNotificationService->dispatch('request.created', [
            'appointment_request' => $requestOne,
        ]);

        $emailNotificationService->dispatch('request.created', [
            'appointment_request' => $requestTwo,
        ]);

        Notification::assertSentOnDemandTimes(RequestCreatedNotification::class, 2);
        $this->assertDatabaseCount('email_notifications', 3);
        $this->assertEquals(1, \App\Models\EmailNotification::query()
            ->where('notification_type', 'request.created.branch')
            ->where('recipient_type', 'branch')
            ->count());
    }

    public function test_request_accept_and_reject_dispatch_expected_notifications(): void
    {
        Notification::fake();

        ['branch' => $branch, 'service' => $service] = $this->createFixture();
        $emailNotificationService = app(EmailNotificationService::class);

        $request = $this->createAppointmentRequest($branch, $service, 'patient@example.com');

        $event = Event::query()->create([
            'branch_id' => $branch->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-03 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-03 10:30:00'),
            'timezone' => config('app.timezone', 'Europe/Bratislava'),
            'is_recurring' => false,
        ]);

        $event->bookingDetail()->create([
            'patient_name' => $request->patient_name,
            'patient_email' => $request->patient_email,
            'patient_phone' => $request->patient_phone,
            'booking_status' => 'confirmed',
        ]);

        $event->services()->sync([
            $service->id => [
                'duration_minutes_snapshot' => 30,
                'price_snapshot' => null,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);

        $emailNotificationService->dispatch('request.accepted_as_booking', [
            'appointment_request' => $request,
            'event' => $event,
        ]);

        $emailNotificationService->dispatch('request.rejected', [
            'appointment_request' => $request,
            'reason' => 'Kapacitne dovody',
        ]);

        Notification::assertSentOnDemandTimes(BookingCreatedNotification::class, 1);
        Notification::assertSentOnDemandTimes(RequestRejectedNotification::class, 1);
        $this->assertDatabaseHas('email_notifications', [
            'notification_type' => 'request.accepted_as_booking',
            'recipient_email' => 'patient@example.com',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('email_notifications', [
            'notification_type' => 'request.rejected',
            'recipient_email' => 'patient@example.com',
            'status' => 'sent',
        ]);
    }

    private function createFixture(): array
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-request-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Request Branch',
            'slug' => 'request-branch-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
            'notification_settings' => [
                'is_enabled' => true,
                'notify_new_appointment_request' => true,
                'notification_emails' => ['branch@example.com'],
            ],
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

        return compact('branch', 'service');
    }

    private function createAppointmentRequest(Branch $branch, Service $service, string $email): AppointmentRequest
    {
        $appointmentRequest = AppointmentRequest::query()->create([
            'branch_id' => $branch->id,
            'request_type' => 'general',
            'preferred_date' => Carbon::parse('2026-07-03')->toDateString(),
            'preferred_period' => 'morning',
            'total_duration_minutes' => 30,
            'patient_name' => 'Patient',
            'patient_email' => $email,
            'patient_phone' => '+421900111111',
            'status' => 'pending',
        ]);

        $appointmentRequest->services()->sync([
            $service->id => [
                'duration_minutes_snapshot' => 30,
                'price_snapshot' => null,
            ],
        ]);

        return $appointmentRequest->fresh(['branch', 'services']);
    }
}
