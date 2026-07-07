<?php

namespace Tests\Feature\Calendar;

use App\Models\Branch;
use App\Models\Company;
use App\Models\OpeningHour;
use App\Models\OpeningHourInterval;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventReadAdapterService;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_reservation_rule_can_be_created(): void
    {
        $fixture = $this->createFixture();

        $response = $this->actingAs($fixture['user'])->put(route('branches.booking.rules.update', $fixture['branch']->id), [
            'rules' => [[
                'id' => null,
                'date' => '2026-07-07',
                'starts_at' => '09:00',
                'ends_at' => '11:00',
                'service_ids' => [$fixture['service']->id],
                'public_booking_type' => 'immediate_booking',
                'repeats' => true,
                'repeat_every' => 1,
                'repeat_unit' => 'weeks',
                'repeat_weekdays' => ['MO'],
                'repeat_ends_on' => '2026-09-30',
                'excluded_dates' => [],
                'is_enabled' => true,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $rule = Event::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('type', EventType::AvailabilityRule)
            ->firstOrFail();

        $this->assertTrue($rule->is_recurring);
        $this->assertSame('2026-07-07 09:00:00', $rule->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-07 11:00:00', $rule->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame('weekly', $rule->recurrence_rule['frequency'] ?? null);
        $this->assertSame(1, $rule->recurrence_rule['interval'] ?? null);
        $this->assertSame(['MO'], $rule->recurrence_rule['weekdays'] ?? []);
        $this->assertSame('2026-09-30', $rule->recurrence_rule['ends']['until'] ?? null);
        $this->assertNotEmpty(data_get($rule->metadata, 'series_uuid'));
    }

    public function test_single_occurrence_can_be_excluded_without_deleting_series(): void
    {
        $fixture = $this->createFixture();

        $rule = $this->createRecurringRule($fixture);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.rules.exclude-date', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'date' => '2026-07-14',
        ]);

        $response->assertSessionHasNoErrors();

        $rule->refresh();

        $this->assertSame(1, $rule->recurrenceChildren()->count());
        $this->assertTrue($rule->is_recurring);
    }

    public function test_single_occurrence_delete_endpoint_creates_cancelled_override(): void
    {
        $fixture = $this->createFixture();

        $rule = $this->createRecurringRule($fixture);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.rules.exclude-date', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'date' => '2026-07-14',
        ]);

        $response->assertSessionHasNoErrors();

        $rule->refresh();

        $override = $rule->recurrenceChildren()->first();

        $this->assertNotNull($override);
        $this->assertSame('cancelled', $override->status);
        $this->assertSame('2026-07-14 09:00:00', $override->recurrence_original_starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-14 09:00:00', $override->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_legacy_payload_keeps_cancelled_override_for_deleted_occurrence(): void
    {
        $fixture = $this->createFixture();

        $rule = $this->createRecurringRule($fixture, [], '2026-07-08');

        $this->actingAs($fixture['user'])->post(route('branches.booking.rules.exclude-date', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'date' => '2026-07-08',
        ])->assertSessionHasNoErrors();

        $payload = app(EventReadAdapterService::class)->getLegacyCalendarPayload(
            $fixture['branch'],
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
        );

        $availabilityRule = collect($payload['availabilityRules'])->firstWhere('id', $rule->id);

        $this->assertNotNull($availabilityRule);
        $this->assertNotEmpty($availabilityRule['occurrence_overrides'] ?? []);
        $this->assertSame([
            [
                'root_event_id' => $rule->id,
                'original_date' => '2026-07-08',
                'date' => '2026-07-08',
                'starts_at' => '09:00',
                'ends_at' => '11:00',
                'status' => 'cancelled',
            ],
        ], $availabilityRule['occurrence_overrides']);
    }

    public function test_rescheduling_single_occurrence_creates_exception_copy_and_keeps_series(): void
    {
        $fixture = $this->createFixture();
        $rule = $this->createRecurringRule($fixture);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.rules.reschedule', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'occurrence_date' => '2026-07-14',
            'date' => '2026-07-16',
            'starts_at' => '14:00',
            'ends_at' => '16:00',
            'reschedule_scope' => 'occurrence',
        ]);

        $response->assertSessionHasNoErrors();

        $rule->refresh();

        $this->assertSame(1, $rule->recurrenceChildren()->count());
        $this->assertTrue($rule->is_recurring);

        $exceptionRule = Event::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('type', EventType::AvailabilityRule)
            ->where('id', '!=', $rule->id)
            ->firstOrFail();

        $this->assertFalse($exceptionRule->is_recurring);
        $this->assertSame('2026-07-16 14:00:00', $exceptionRule->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-16 16:00:00', $exceptionRule->ends_at?->format('Y-m-d H:i:s'));
    }

    public function test_rescheduling_series_updates_whole_rule_without_creating_new_rule(): void
    {
        $fixture = $this->createFixture();
        $rule = $this->createRecurringRule($fixture);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.rules.reschedule', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'occurrence_date' => '2026-07-14',
            'date' => '2026-07-09',
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'reschedule_scope' => 'series',
        ]);

        $response->assertSessionHasNoErrors();

        $rule->refresh();

        $this->assertSame('2026-07-09 10:00:00', $rule->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-09 12:00:00', $rule->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame(1, Event::query()->where('branch_id', $fixture['branch']->id)->where('type', EventType::AvailabilityRule)->count());
    }

    public function test_rescheduling_series_shifts_weekly_custom_weekdays(): void
    {
        $fixture = $this->createFixture();
        $rule = $this->createRecurringRule($fixture, ['TH', 'FR'], '2026-07-09');

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.rules.reschedule', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'occurrence_date' => '2026-07-09',
            'date' => '2026-07-13',
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'reschedule_scope' => 'series',
        ]);

        $response->assertSessionHasNoErrors();

        $rule->refresh();

        $this->assertSame('2026-07-13 10:00:00', $rule->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-13 12:00:00', $rule->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame(['TH', 'FR'], $rule->recurrence_rule['weekdays'] ?? []);
    }

    public function test_rescheduling_from_date_splits_rule_series(): void
    {
        $fixture = $this->createFixture();
        $rule = $this->createRecurringRule($fixture);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.rules.reschedule', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'occurrence_date' => '2026-07-21',
            'date' => '2026-07-22',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'reschedule_scope' => 'from_date',
        ]);

        $response->assertSessionHasNoErrors();

        $rule->refresh();

        $this->assertSame('2026-07-14', $rule->recurrence_rule['ends']['until'] ?? null);

        $newRule = Event::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('type', EventType::AvailabilityRule)
            ->where('id', '!=', $rule->id)
            ->firstOrFail();

        $this->assertSame('2026-07-22 13:00:00', $newRule->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-22 15:00:00', $newRule->ends_at?->format('Y-m-d H:i:s'));
        $this->assertTrue($newRule->is_recurring);
    }

    public function test_delete_from_date_keeps_past_occurrences_only(): void
    {
        $fixture = $this->createFixture();
        $rule = $this->createRecurringRule($fixture);

        $response = $this->actingAs($fixture['user'])->post(route('branches.booking.rules.end-before-date', [
            $fixture['branch']->id,
            $rule->id,
        ]), [
            'date' => '2026-07-21',
        ]);

        $response->assertSessionHasNoErrors();

        $rule->refresh();

        $this->assertSame('2026-07-14', $rule->recurrence_rule['ends']['until'] ?? null);
    }

    public function test_delete_all_occurrences_removes_rule(): void
    {
        $fixture = $this->createFixture();
        $rule = $this->createRecurringRule($fixture);

        $response = $this->actingAs($fixture['user'])->delete(route('branches.booking.rules.destroy', [
            $fixture['branch']->id,
            $rule->id,
        ]));

        $response->assertSessionHasNoErrors();

        $this->assertSoftDeleted((new Event())->getTable(), ['id' => $rule->id]);
    }

    public function test_rule_cannot_be_created_on_closed_weekend_day(): void
    {
        $fixture = $this->createFixture();

        OpeningHour::query()->create([
            'branch_id' => $fixture['branch']->id,
            'day_of_week' => 1,
            'is_closed' => false,
        ]);

        $monday = OpeningHour::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('day_of_week', 1)
            ->firstOrFail();

        OpeningHourInterval::query()->create([
            'opening_hour_id' => $monday->id,
            'opens_at' => '09:00:00',
            'closes_at' => '12:00:00',
        ]);

        OpeningHour::query()->create([
            'branch_id' => $fixture['branch']->id,
            'day_of_week' => 6,
            'is_closed' => true,
        ]);

        OpeningHour::query()->create([
            'branch_id' => $fixture['branch']->id,
            'day_of_week' => 7,
            'is_closed' => true,
        ]);

        $response = $this->actingAs($fixture['user'])->put(route('branches.booking.rules.update', $fixture['branch']->id), [
            'rules' => [[
                'id' => null,
                'date' => '2026-07-11',
                'starts_at' => '09:00',
                'ends_at' => '11:00',
                'service_ids' => [$fixture['service']->id],
                'public_booking_type' => 'immediate_booking',
                'repeats' => false,
                'repeat_every' => 1,
                'repeat_unit' => 'weeks',
                'repeat_ends_on' => null,
                'excluded_dates' => [],
                'is_enabled' => true,
            ]],
        ]);

        $response->assertSessionHasErrors(['rules.0.starts_at']);
    }

    private function createRecurringRule(array $fixture, array $repeatWeekdays = [], string $date = '2026-07-07'): Event
    {
        $this->actingAs($fixture['user'])->put(route('branches.booking.rules.update', $fixture['branch']->id), [
            'rules' => [[
                'id' => null,
                'date' => $date,
                'starts_at' => '09:00',
                'ends_at' => '11:00',
                'service_ids' => [$fixture['service']->id],
                'public_booking_type' => 'immediate_booking',
                'repeats' => true,
                'repeat_every' => 1,
                'repeat_unit' => 'weeks',
                'repeat_weekdays' => $repeatWeekdays,
                'repeat_ends_on' => '2026-09-30',
                'excluded_dates' => [],
                'is_enabled' => true,
            ]],
        ])->assertSessionHasNoErrors();

        return Event::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('type', EventType::AvailabilityRule)
            ->firstOrFail();
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
            'slug' => 'clinvia-rule-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Rules Branch',
            'slug' => 'rules-branch-' . Str::random(6),
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
