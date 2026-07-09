<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Services\RecurringImpactService;
use Carbon\Carbon;

class RecurringImpactServiceTest extends RecurringEventsTestCase
{
    public function test_scope_this_always_returns_one_affected_occurrence(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $occurrence = $this->renderRange($fixture['branch'])->first();
        $preview = $this->preview($fixture['branch'], $occurrence, 'edit', 'this');

        $this->assertSame(1, $preview['affected_occurrence_count']);
        $this->assertSame('1', $preview['affected_occurrence_count_label']);
        $this->assertFalse((bool) ($preview['is_affected_count_capped'] ?? false));
        $this->assertSame('this', $preview['scope']);
        $this->assertSame($master->root_event_id ?? $master->id, $preview['root_event_id']);
    }

    public function test_scope_all_after_split_counts_logical_root_occurrences_across_segments(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(4),
        ]);

        $splitMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 12:00:00',
            'ends_at' => '2026-07-13 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $occurrence = $this->renderRange($fixture['branch'])
            ->first(fn (array $item): bool => (int) ($item['event']->id ?? 0) === (int) $splitMaster->id);

        $preview = $this->preview($fixture['branch'], $occurrence, 'delete', 'all');

        $this->assertSame(3, $preview['affected_occurrence_count']);
        $this->assertSame('3', $preview['affected_occurrence_count_label']);
        $this->assertFalse((bool) ($preview['is_affected_count_capped'] ?? false));
    }

    public function test_scope_all_after_split_counts_all_occurrences_for_non_booking_event_root(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(4),
        ]);

        $splitMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 12:00:00',
            'ends_at' => '2026-07-13 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $occurrence = $this->renderRange($fixture['branch'])
            ->first(fn (array $item): bool => (int) ($item['event']->id ?? 0) === (int) $splitMaster->id);

        $preview = $this->preview($fixture['branch'], $occurrence, 'delete', 'all');

        $this->assertSame(4, $preview['affected_occurrence_count']);
        $this->assertSame('4', $preview['affected_occurrence_count_label']);
    }

    public function test_scope_this_and_following_after_multiple_splits_counts_across_all_segments_with_same_root(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO', 'TH'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-23',
                ],
            ],
        ]);

        $splitB = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 11:00:00',
            'ends_at' => '2026-07-13 12:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $this->mutationService()->update($splitB->fresh(), [
            'occurrence_date' => '2026-07-20',
            'starts_at' => '2026-07-20 12:00:00',
            'ends_at' => '2026-07-20 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $selected = $this->renderRange($fixture['branch'])
            ->first(fn (array $item): bool => ($item['occurrence_starts_at'] ?? null)?->toDateString() === '2026-07-13');

        $preview = $this->preview($fixture['branch'], $selected, 'reschedule', 'this_and_following');

        $this->assertSame(4, $preview['affected_occurrence_count']);
        $this->assertSame('4', $preview['affected_occurrence_count_label']);
    }

    public function test_cancelled_exception_is_not_counted_as_visible_affected_occurrence(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-13',
        ], fn () => $this->mutationService()->delete($master, 'this'));

        $selected = $this->renderRange($fixture['branch'])->first();
        $preview = $this->preview($fixture['branch'], $selected, 'delete', 'all');

        $starts = collect($preview['affected_occurrences'])->pluck('starts_at')->all();
        $this->assertFalse(collect($starts)->contains(fn ($value) => str_contains((string) $value, '2026-07-13')));
    }

    public function test_moved_exception_is_counted_once_using_actual_start_and_no_duplicate_display_key(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->mutationService()->reschedule(
            $master,
            Carbon::parse('2026-07-15 14:00:00', 'Europe/Bratislava'),
            Carbon::parse('2026-07-15 15:00:00', 'Europe/Bratislava'),
            $fixture['user']->id,
            'this',
            Carbon::parse('2026-07-13', 'Europe/Bratislava'),
        );

        $selected = $this->renderRange($fixture['branch'])->first();
        $preview = $this->preview($fixture['branch'], $selected, 'reschedule', 'all');

        $displayKeys = collect($preview['affected_occurrences'])->pluck('display_key')->all();
        $this->assertSame(count(array_unique($displayKeys)), count($displayKeys));

        $starts = collect($preview['affected_occurrences'])->pluck('starts_at')->all();
        $this->assertTrue(collect($starts)->contains(fn ($value) => str_contains((string) $value, '2026-07-15')));
        $this->assertFalse(collect($starts)->contains(fn ($value) => str_contains((string) $value, '2026-07-13')));
    }

    public function test_infinite_series_uses_next_twelve_month_preview_and_sets_infinite_flag(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'never',
                    'count' => null,
                    'until' => null,
                ],
            ],
        ]);

        $selected = $this->renderRange(
            $fixture['branch'],
            Carbon::parse('2026-07-01', 'Europe/Bratislava'),
            Carbon::parse('2026-07-31', 'Europe/Bratislava'),
        )->first();

        $preview = $this->preview($fixture['branch'], $selected, 'edit', 'all');

        $this->assertTrue((bool) $preview['is_infinite_series']);
        $this->assertStringContainsString('12 mesiacoch', (string) $preview['message']);
    }

    public function test_count_series_returns_exact_occurrence_count(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(6),
        ]);

        $selected = $this->renderRange($fixture['branch'])->first();
        $preview = $this->preview($fixture['branch'], $selected, 'edit', 'all');

        $this->assertSame(5, $preview['affected_occurrence_count']);
        $this->assertSame('5', $preview['affected_occurrence_count_label']);
    }

    public function test_until_series_returns_exact_occurrence_count_until_until_date(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-07-27',
                ],
            ],
        ]);

        $selected = $this->renderRange($fixture['branch'])->first();
        $preview = $this->preview($fixture['branch'], $selected, 'edit', 'all');

        $this->assertSame(3, $preview['affected_occurrence_count']);
        $this->assertSame('3', $preview['affected_occurrence_count_label']);
    }

    public function test_preview_caps_occurrence_count_at_fifty_plus_when_more_than_limit(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => Carbon::parse('2026-08-03 09:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-08-03 10:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(100),
        ]);

        $selected = $this->renderRange(
            $fixture['branch'],
            Carbon::parse('2026-08-01', 'Europe/Bratislava'),
            Carbon::parse('2026-09-30', 'Europe/Bratislava'),
        )->first();

        $preview = $this->preview($fixture['branch'], $selected, 'delete', 'all');

        $this->assertSame(50, $preview['affected_occurrence_count']);
        $this->assertSame('50+', $preview['affected_occurrence_count_label']);
        $this->assertTrue((bool) ($preview['is_affected_count_capped'] ?? false));
        $this->assertCount(50, $preview['affected_occurrences']);
        $this->assertCount(5, $preview['affected_occurrence_sample']);
    }

    public function test_preview_returns_exact_fifty_without_cap_flag(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => Carbon::parse('2026-08-03 09:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-08-03 10:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(51),
        ]);

        $selected = $this->renderRange(
            $fixture['branch'],
            Carbon::parse('2026-08-01', 'Europe/Bratislava'),
            Carbon::parse('2026-09-30', 'Europe/Bratislava'),
        )->first();

        $preview = $this->preview($fixture['branch'], $selected, 'delete', 'all');

        $this->assertSame(50, $preview['affected_occurrence_count']);
        $this->assertSame('50', $preview['affected_occurrence_count_label']);
        $this->assertFalse((bool) ($preview['is_affected_count_capped'] ?? false));
    }

    public function test_preview_caps_when_occurrence_count_is_fifty_one(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => Carbon::parse('2026-08-03 09:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-08-03 10:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(52),
        ]);

        $selected = $this->renderRange(
            $fixture['branch'],
            Carbon::parse('2026-08-01', 'Europe/Bratislava'),
            Carbon::parse('2026-09-30', 'Europe/Bratislava'),
        )->first();

        $preview = $this->preview($fixture['branch'], $selected, 'delete', 'all');

        $this->assertSame(50, $preview['affected_occurrence_count']);
        $this->assertSame('50+', $preview['affected_occurrence_count_label']);
        $this->assertTrue((bool) ($preview['is_affected_count_capped'] ?? false));
    }

    public function test_booking_returns_past_occurrence_count_not_modified(): void
    {
        $fixture = $this->createCalendarFixture();

        $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-06-01 10:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-06-01 11:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'on',
                    'count' => null,
                    'until' => '2026-08-31',
                ],
            ],
        ]);

        $selected = $this->renderRange(
            $fixture['branch'],
            Carbon::parse('2026-07-01', 'Europe/Bratislava'),
            Carbon::parse('2026-07-31', 'Europe/Bratislava'),
        )->first();

        $preview = $this->preview($fixture['branch'], $selected, 'delete', 'all');

        $this->assertGreaterThan(0, (int) $preview['past_occurrence_count_not_modified']);
    }

    public function test_availability_rule_returns_conflicting_booking_count(): void
    {
        $fixture = $this->createCalendarFixture();

        $availability = $this->createBaseRecurringMaster($fixture, EventType::AvailabilityRule, [
            'starts_at' => Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 11:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(4),
        ]);

        $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'starts_at' => Carbon::parse('2026-07-06 10:30:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 11:00:00', 'Europe/Bratislava'),
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(4),
        ]);

        $selected = $this->renderRange($fixture['branch'])
            ->first(fn (array $item): bool => (int) $item['event']->id === (int) $availability->id);

        $preview = $this->preview($fixture['branch'], $selected, 'delete', 'all');

        $this->assertGreaterThan(0, (int) $preview['affected_conflicting_booking_count']);
    }

    public function test_group_event_returns_affected_participant_count(): void
    {
        $fixture = $this->createCalendarFixture();

        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava'),
            'group_detail' => ['capacity' => 8],
        ]);

        $occurrenceStartsAt = Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava');
        $occurrenceEndsAt = Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava');

        $occurrenceEvent = $this->mutationService()->materializeOccurrence($master, $occurrenceStartsAt, $occurrenceEndsAt, $fixture['user']->id);

        $this->mutationService()->addGroupParticipant($occurrenceEvent, [
            'participant_name' => 'Test Participant',
            'participant_email' => 'participant@example.com',
            'status' => 'confirmed',
        ]);

        $selected = $this->renderRange($fixture['branch'])
            ->first(fn (array $item): bool => (int) $item['event']->id === (int) $occurrenceEvent->id);

        $preview = $this->preview($fixture['branch'], $selected, 'edit', 'this');

        $this->assertSame(1, (int) $preview['affected_participant_count']);
        $this->assertSame('1', (string) ($preview['affected_participant_count_label'] ?? ''));
        $this->assertFalse((bool) ($preview['is_affected_participant_count_capped'] ?? false));
    }

    public function test_group_event_participant_count_caps_at_fifty_plus(): void
    {
        $fixture = $this->createCalendarFixture();

        $master = $this->createBaseRecurringMaster($fixture, EventType::GroupEvent, [
            'starts_at' => Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava'),
            'group_detail' => ['capacity' => 120],
        ]);

        $occurrenceStartsAt = Carbon::parse('2026-07-06 09:00:00', 'Europe/Bratislava');
        $occurrenceEndsAt = Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava');

        $occurrenceEvent = $this->mutationService()->materializeOccurrence($master, $occurrenceStartsAt, $occurrenceEndsAt, $fixture['user']->id);

        foreach (range(1, 51) as $index) {
            $this->mutationService()->addGroupParticipant($occurrenceEvent, [
                'participant_name' => 'Participant '.$index,
                'participant_email' => 'participant'.$index.'@example.com',
                'status' => 'confirmed',
            ]);
        }

        $selected = $this->renderRange($fixture['branch'])
            ->first(fn (array $item): bool => (int) $item['event']->id === (int) $occurrenceEvent->id);

        $preview = $this->preview($fixture['branch'], $selected, 'edit', 'this');

        $this->assertSame(50, (int) $preview['affected_participant_count']);
        $this->assertSame('50+', (string) ($preview['affected_participant_count_label'] ?? ''));
        $this->assertTrue((bool) ($preview['is_affected_participant_count_capped'] ?? false));
    }

    public function test_availability_rule_all_scope_after_split_uses_root_event_occurrence_count(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::AvailabilityRule, [
            'recurrence_rule' => $this->baseWeeklyCountRecurrence(4),
        ]);

        $splitMaster = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 12:00:00',
            'ends_at' => '2026-07-13 13:00:00',
        ], $fixture['user']->id, 'this_and_following');

        $selected = $this->renderRange($fixture['branch'])
            ->first(fn (array $item): bool => (int) ($item['event']->id ?? 0) === (int) $splitMaster->id);

        $preview = $this->preview($fixture['branch'], $selected, 'delete', 'all');

        $this->assertSame(4, (int) $preview['affected_occurrence_count']);
        $this->assertSame('4', (string) ($preview['affected_occurrence_count_label'] ?? ''));
    }

    public function test_preview_always_returns_unique_display_keys(): void
    {
        $fixture = $this->createCalendarFixture();
        $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $selected = $this->renderRange($fixture['branch'])->first();
        $preview = $this->preview($fixture['branch'], $selected, 'edit', 'all');

        $displayKeys = collect($preview['affected_occurrences'])->pluck('display_key')->all();

        $this->assertSame(count(array_unique($displayKeys)), count($displayKeys));
    }

    private function preview($branch, array $occurrence, string $action, string $scope): array
    {
        return app(RecurringImpactService::class)->preview(
            branch: $branch,
            selectedOccurrence: [
                'event_id' => (int) ($occurrence['event']->id ?? 0),
                'root_event_id' => (int) ($occurrence['root_event_id'] ?? 0),
                'occurrence_starts_at' => ($occurrence['occurrence_starts_at'] ?? null)?->toIso8601String(),
                'occurrence_ends_at' => ($occurrence['occurrence_ends_at'] ?? null)?->toIso8601String(),
                'occurrence_original_starts_at' => ($occurrence['occurrence_original_starts_at'] ?? null)?->toIso8601String(),
                'starts_at' => ($occurrence['occurrence_starts_at'] ?? null)?->toIso8601String(),
                'ends_at' => ($occurrence['occurrence_ends_at'] ?? null)?->toIso8601String(),
                'display_key' => $occurrence['display_key'] ?? null,
            ],
            action: $action,
            scope: $scope,
            changes: [],
        );
    }
}
