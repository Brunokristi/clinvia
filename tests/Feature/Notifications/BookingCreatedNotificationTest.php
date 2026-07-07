<?php

namespace Tests\Feature\Notifications;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_created_email_mentions_recurring_series_when_recurrence_exists(): void
    {
        $fixture = $this->createFixture();

        $booking = new Booking([
            'service_id' => $fixture['service']->id,
            'starts_at' => Carbon::parse('2026-07-10 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-10 10:30:00'),
            'patient_name' => 'Recurring Patient',
            'patient_email' => 'patient@example.com',
            'patient_phone' => '+421900000123',
            'status' => 'confirmed',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['FR'],
                'ends' => [
                    'type' => 'after',
                    'count' => 4,
                    'until' => null,
                ],
            ],
            'recurrence_excluded_dates' => [],
        ]);
        $booking->id = 1;
        $booking->setRelation('branch', $fixture['branch']);
        $booking->setRelation('service', $fixture['service']);
        $booking->setRelation('services', collect([$fixture['service']]));

        $mailMessage = (new BookingCreatedNotification($booking))->toMail($fixture['user']);

        $this->assertSame('emails.bookings.created', $mailMessage->view);
        $this->assertTrue((bool) ($mailMessage->viewData['isRecurring'] ?? false));
        $this->assertNotEmpty($mailMessage->rawAttachments);
        $this->assertSame('reservation.ics', $mailMessage->rawAttachments[0]['name'] ?? null);
    }

    public function test_booking_created_email_marks_one_off_booking_as_non_recurring(): void
    {
        $fixture = $this->createFixture();

        $booking = new Booking([
            'service_id' => $fixture['service']->id,
            'starts_at' => Carbon::parse('2026-07-10 10:00:00'),
            'ends_at' => Carbon::parse('2026-07-10 10:30:00'),
            'patient_name' => 'One Off Patient',
            'patient_email' => 'patient2@example.com',
            'patient_phone' => '+421900000124',
            'status' => 'confirmed',
            'recurrence' => null,
            'recurrence_excluded_dates' => [],
        ]);
        $booking->id = 2;
        $booking->setRelation('branch', $fixture['branch']);
        $booking->setRelation('service', $fixture['service']);
        $booking->setRelation('services', collect([$fixture['service']]));

        $mailMessage = (new BookingCreatedNotification($booking))->toMail($fixture['user']);

        $this->assertSame('emails.bookings.created', $mailMessage->view);
        $this->assertFalse((bool) ($mailMessage->viewData['isRecurring'] ?? true));
        $this->assertNotEmpty($mailMessage->rawAttachments);
        $this->assertSame('reservation.ics', $mailMessage->rawAttachments[0]['name'] ?? null);
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
            'slug' => 'clinvia-notify-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Notify Branch',
            'slug' => 'notify-branch-' . Str::random(6),
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

        return compact('user', 'company', 'branch', 'service');
    }
}
