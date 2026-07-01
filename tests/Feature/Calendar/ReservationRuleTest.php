<?php

namespace Tests\Feature\Calendar;

use App\Models\BookingAvailabilityRule;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
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
                'repeat_ends_on' => '2026-09-30',
                'excluded_dates' => [],
                'is_enabled' => true,
            ]],
        ]);

        $response->assertSessionHasNoErrors();

        $rule = BookingAvailabilityRule::query()
            ->where('branch_id', $fixture['branch']->id)
            ->firstOrFail();

        $this->assertTrue($rule->repeats);
        $this->assertSame(1, $rule->repeat_every);
        $this->assertSame('weeks', $rule->repeat_unit);
        $this->assertSame('2026-07-07', $rule->date?->toDateString());
        $this->assertSame('2026-09-30', $rule->repeat_ends_on?->toDateString());
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

        $this->assertContains('2026-07-14', $rule->excluded_dates ?? []);
        $this->assertTrue($rule->repeats);
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

        $this->assertContains('2026-07-14', $rule->excluded_dates ?? []);
        $this->assertTrue($rule->repeats);

        $exceptionRule = BookingAvailabilityRule::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('id', '!=', $rule->id)
            ->firstOrFail();

        $this->assertFalse($exceptionRule->repeats);
        $this->assertSame('2026-07-16', $exceptionRule->date?->toDateString());
        $this->assertSame('14:00', $exceptionRule->starts_at);
        $this->assertSame('16:00', $exceptionRule->ends_at);
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

        $this->assertSame('2026-07-09', $rule->date?->toDateString());
        $this->assertSame('10:00', $rule->starts_at);
        $this->assertSame('12:00', $rule->ends_at);
        $this->assertSame(1, BookingAvailabilityRule::query()->where('branch_id', $fixture['branch']->id)->count());
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

        $this->assertSame('2026-07-20', $rule->repeat_ends_on?->toDateString());

        $newRule = BookingAvailabilityRule::query()
            ->where('branch_id', $fixture['branch']->id)
            ->where('id', '!=', $rule->id)
            ->firstOrFail();

        $this->assertSame('2026-07-22', $newRule->date?->toDateString());
        $this->assertSame('13:00', $newRule->starts_at);
        $this->assertSame('15:00', $newRule->ends_at);
        $this->assertTrue($newRule->repeats);
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

        $this->assertSame('2026-07-20', $rule->repeat_ends_on?->toDateString());
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

        $this->assertDatabaseMissing('booking_availability_rules', [
            'id' => $rule->id,
        ]);
    }

    private function createRecurringRule(array $fixture): BookingAvailabilityRule
    {
        $this->actingAs($fixture['user'])->put(route('branches.booking.rules.update', $fixture['branch']->id), [
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
                'repeat_ends_on' => '2026-09-30',
                'excluded_dates' => [],
                'is_enabled' => true,
            ]],
        ])->assertSessionHasNoErrors();

        return BookingAvailabilityRule::query()
            ->where('branch_id', $fixture['branch']->id)
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
