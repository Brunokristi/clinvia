<?php

namespace Tests\Feature\Notifications;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Service;
use App\Notifications\BookingCancelledNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingCancelledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_notification_attaches_cancel_ics(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Clinvia Clinic',
            'slug' => 'clinvia-cancel-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Cancel Branch',
            'slug' => 'cancel-branch-' . Str::random(6),
            'type' => 'clinic',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Konzultacia',
            'slug' => 'konzultacia-cancel-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        $booking = new Booking([
            'branch_id' => $branch->id,
            'service_id' => $service->id,
            'patient_name' => 'Test Patient',
            'patient_email' => 'patient@example.com',
            'starts_at' => Carbon::parse('2026-08-12 10:30:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-08-12 11:00:00', 'Europe/Bratislava'),
            'booking_slot_id' => null,
            'capacity_window_id' => null,
        ]);
        $booking->id = 1;
        $booking->setRelation('branch', $branch);
        $booking->setRelation('service', $service);
        $booking->setRelation('services', collect([$service]));

        $mail = (new BookingCancelledNotification(
            booking: $booking,
            reason: 'Zrusene ambulanciou',
            appointmentStartsAt: Carbon::parse('2026-08-12 10:30:00', 'Europe/Bratislava'),
            appointmentEndsAt: Carbon::parse('2026-08-12 11:00:00', 'Europe/Bratislava'),
        ))->toMail((object) []);

        $this->assertNotEmpty($mail->rawAttachments);
        $this->assertSame('reservation-cancelled.ics', $mail->rawAttachments[0]['name'] ?? null);

        $ics = (string) ($mail->rawAttachments[0]['data'] ?? '');
        $this->assertStringContainsString('METHOD:CANCEL', $ics);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
        $this->assertStringContainsString('UID:booking-' . $booking->id . '@clinvia.local', $ics);
    }
}
