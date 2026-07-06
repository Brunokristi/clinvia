<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\GroupEventParticipant;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait CreatesCalendarFixtures
{
    protected function createCalendarFixture(array $branchSettings = []): array
    {
        $user = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'calendar-admin-' . Str::random(8) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Clinvia Calendar Company',
            'slug' => 'clinvia-calendar-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Calendar Branch',
            'slug' => 'calendar-branch-' . Str::random(8),
            'type' => 'clinic',
            'is_active' => true,
            'booking_settings' => array_replace_recursive([
                'is_enabled' => true,
                'calendar_addon_enabled' => true,
                'booking_addon_enabled' => true,
            ], $branchSettings),
        ]);

        $service = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Calendar Service',
            'slug' => 'calendar-service-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 5,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        return compact('user', 'company', 'branch', 'service');
    }

    protected function createBookingEvent(array $fixture, array $overrides = []): Event
    {
        $event = $this->createEventRecord($fixture, array_replace([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::Booking->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 09:30:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
            'metadata' => [],
        ], $overrides));

        $event->bookingDetail()->create([
            ...$this->bookingDetailDefaults(),
            ...(Arr::pull($overrides, 'booking_detail', [])),
        ]);

        $this->syncDefaultService($event, $fixture['service']);

        return $event->fresh(['bookingDetail', 'services']);
    }

    protected function createAvailabilityRuleEvent(array $fixture, array $overrides = []): Event
    {
        $event = $this->createEventRecord($fixture, array_replace([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::AvailabilityRule->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 09:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 12:00:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
            'metadata' => [],
        ], $overrides));

        $event->availabilityRuleDetail()->create([
            'slot_interval_minutes' => 15,
            ...(Arr::pull($overrides, 'availability_rule_detail', [])),
        ]);

        $this->syncDefaultService($event, $fixture['service']);

        return $event->fresh(['availabilityRuleDetail', 'services']);
    }

    protected function createGroupEvent(array $fixture, array $overrides = []): Event
    {
        $event = $this->createEventRecord($fixture, array_replace([
            'branch_id' => $fixture['branch']->id,
            'type' => EventType::GroupEvent->value,
            'status' => 'confirmed',
            'starts_at' => Carbon::parse('2026-07-20 14:00:00'),
            'ends_at' => Carbon::parse('2026-07-20 15:00:00'),
            'timezone' => config('app.timezone'),
            'is_recurring' => false,
            'metadata' => [],
        ], $overrides));

        $event->groupDetail()->create([
            'service_id' => $fixture['service']->id,
            'service_name' => $fixture['service']->name,
            'capacity' => 5,
            'reserved_places' => 0,
            'group_status' => 'confirmed',
            ...(Arr::pull($overrides, 'group_detail', [])),
        ]);

        $this->syncDefaultService($event, $fixture['service']);

        return $event->fresh(['groupDetail', 'services', 'participants']);
    }

    protected function createRecurringOverride(Event $rootEvent, array $overrides = []): Event
    {
        $occurrenceStartsAt = isset($overrides['recurrence_original_starts_at'])
            ? Carbon::parse($overrides['recurrence_original_starts_at'])
            : $rootEvent->starts_at?->copy();
        $occurrenceEndsAt = isset($overrides['recurrence_original_ends_at'])
            ? Carbon::parse($overrides['recurrence_original_ends_at'])
            : $rootEvent->ends_at?->copy();

        $override = Event::query()->create(array_replace([
            'branch_id' => $rootEvent->branch_id,
            'type' => $rootEvent->type->value,
            'status' => $rootEvent->status,
            'starts_at' => $occurrenceStartsAt,
            'ends_at' => $occurrenceEndsAt,
            'timezone' => $rootEvent->timezone,
            'title' => $rootEvent->title,
            'description' => $rootEvent->description,
            'recurrence_parent_id' => $rootEvent->id,
            'recurrence_original_starts_at' => $occurrenceStartsAt,
            'recurrence_original_ends_at' => $occurrenceEndsAt,
            'recurrence_rule' => null,
            'is_recurring' => false,
            'metadata' => $rootEvent->metadata ?? [],
        ], $overrides));

        if ($rootEvent->bookingDetail) {
            $override->bookingDetail()->create($rootEvent->bookingDetail->only([
                'patient_id',
                'booking_source',
                'booking_status',
                'internal_notes',
                'public_notes',
                'patient_name',
                'patient_email',
                'patient_phone',
                'patient_birth_number',
                'contact_snapshot',
            ]));
        }

        if ($rootEvent->availabilityRuleDetail) {
            $override->availabilityRuleDetail()->create($rootEvent->availabilityRuleDetail->only([
                'capacity_rules',
                'visibility_rules',
                'min_booking_notice_minutes',
                'max_booking_notice_minutes',
                'slot_interval_minutes',
                'buffer_before_minutes',
                'buffer_after_minutes',
                'online_booking_rules',
            ]));
        }

        if ($rootEvent->groupDetail) {
            $override->groupDetail()->create($rootEvent->groupDetail->only([
                'service_id',
                'service_name',
                'capacity',
                'reserved_places',
                'group_status',
                'notes',
            ]));
        }

        if ($rootEvent->services()->count() > 0) {
            $override->services()->sync($rootEvent->services->mapWithKeys(function (Service $service) {
                return [
                    $service->id => [
                        'duration_minutes_snapshot' => $service->pivot?->duration_minutes_snapshot,
                        'price_snapshot' => $service->pivot?->price_snapshot,
                        'sort_order' => $service->pivot?->sort_order ?? 0,
                        'quantity' => $service->pivot?->quantity ?? 1,
                    ],
                ];
            })->all());
        }

        return $override->fresh(['bookingDetail', 'availabilityRuleDetail', 'groupDetail', 'services', 'participants']);
    }

    protected function addGroupParticipant(Event $event, array $overrides = []): GroupEventParticipant
    {
        $participant = $event->participants()->create(array_replace([
            'status' => 'confirmed',
            'booked_at' => now(),
            'participant_name' => 'Group Participant',
            'participant_email' => 'group.participant@example.com',
            'participant_phone' => '+421900654321',
        ], $overrides));

        if ($event->groupDetail) {
            $event->groupDetail()->increment('reserved_places');
        }

        return $participant;
    }

    protected function weeklyRecurrence(array $weekdays = ['MO'], int $interval = 1, array $ends = ['type' => 'never', 'count' => null, 'until' => null]): array
    {
        return [
            'frequency' => 'weekly',
            'interval' => $interval,
            'weekdays' => $weekdays,
            'ends' => $ends,
        ];
    }

    protected function dailyRecurrence(int $interval = 1, array $ends = ['type' => 'never', 'count' => null, 'until' => null]): array
    {
        return [
            'frequency' => 'daily',
            'interval' => $interval,
            'weekdays' => [],
            'ends' => $ends,
        ];
    }

    protected function monthlyRecurrence(int $interval = 1, array $ends = ['type' => 'never', 'count' => null, 'until' => null]): array
    {
        return [
            'frequency' => 'monthly',
            'interval' => $interval,
            'weekdays' => [],
            'ends' => $ends,
        ];
    }

    private function createEventRecord(array $fixture, array $attributes): Event
    {
        return Event::query()->create($attributes);
    }

    private function bookingDetailDefaults(): array
    {
        return [
            'patient_name' => 'Fixture Patient',
            'patient_email' => 'fixture.patient@example.com',
            'patient_phone' => '+421900123456',
            'booking_status' => 'confirmed',
        ];
    }

    private function syncDefaultService(Event $event, Service $service): void
    {
        $event->services()->sync([
            $service->id => [
                'duration_minutes_snapshot' => $service->duration_minutes,
                'price_snapshot' => $service->self_pay_amount,
                'sort_order' => 0,
                'quantity' => 1,
            ],
        ]);
    }
}