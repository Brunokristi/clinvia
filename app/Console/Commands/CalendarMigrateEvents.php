<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingAvailabilityRule;
use App\Models\CapacityWindow;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalendarMigrateEvents extends Command
{
    protected $signature = 'calendar:migrate-events
        {--branch= : Branch id filter}
        {--dry-run : Preview migration without writes}';

    protected $description = 'Migrate legacy booking/rule/capacity data into unified events tables.';

    public function handle(): int
    {
        $branchId = $this->option('branch');
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'bookings_total' => 0,
            'bookings_migrated' => 0,
            'rules_total' => 0,
            'rules_migrated' => 0,
            'capacity_total' => 0,
            'capacity_migrated' => 0,
            'skipped_existing_map' => 0,
        ];

        $this->info('Starting calendar migration' . ($dryRun ? ' (dry-run)' : ''));

        $runner = function () use ($branchId, $dryRun, &$stats): void {
            $this->migrateBookings($branchId, $dryRun, $stats);
            $this->migrateAvailabilityRules($branchId, $dryRun, $stats);
            $this->migrateCapacityWindows($branchId, $dryRun, $stats);
        };

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $runner();
                DB::rollBack();
            } catch (\Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }
        } else {
            DB::transaction($runner);
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($count, $key) => [$key, $count])->values()->all());

        $this->info('Migration finished.');

        return self::SUCCESS;
    }

    private function migrateBookings(?string $branchId, bool $dryRun, array &$stats): void
    {
        Booking::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->with(['services', 'service'])
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($dryRun, &$stats): void {
                foreach ($bookings as $booking) {
                    $stats['bookings_total']++;

                    if ($this->hasLegacyMapping('booking', $booking->id)) {
                        $stats['skipped_existing_map']++;
                        continue;
                    }

                    if ($dryRun) {
                        $stats['bookings_migrated']++;
                        continue;
                    }

                    $event = Event::query()->create([
                        'branch_id' => $booking->branch_id,
                        'type' => EventType::Booking,
                        'status' => $booking->status,
                        'starts_at' => $booking->starts_at,
                        'ends_at' => $booking->ends_at,
                        'timezone' => config('app.timezone'),
                        'title' => null,
                        'description' => null,
                        'recurrence_rule' => $booking->recurrence,
                        'is_recurring' => ! empty($booking->recurrence),
                        'metadata' => [
                            'series_uuid' => $booking->series_uuid,
                            'recurrence_excluded_dates' => $booking->recurrence_excluded_dates ?? [],
                            'legacy_booking_id' => $booking->id,
                        ],
                    ]);

                    $event->bookingDetail()->create([
                        'patient_id' => null,
                        'booking_source' => 'legacy_booking',
                        'booking_status' => $booking->status,
                        'internal_notes' => $booking->admin_note,
                        'public_notes' => $booking->patient_note,
                        'patient_name' => $booking->patient_name,
                        'patient_email' => $booking->patient_email,
                        'patient_phone' => $booking->patient_phone,
                        'patient_birth_number' => $booking->patient_birth_number,
                        'contact_snapshot' => [
                            'name' => $booking->patient_name,
                            'email' => $booking->patient_email,
                            'phone' => $booking->patient_phone,
                            'birth_number' => $booking->patient_birth_number,
                        ],
                    ]);

                    $serviceRows = $booking->services->isNotEmpty()
                        ? $booking->services
                        : collect($booking->service ? [$booking->service] : []);

                    $syncPayload = [];

                    foreach ($serviceRows as $index => $service) {
                        $syncPayload[$service->id] = [
                            'duration_minutes_snapshot' => $service->pivot->duration_minutes_snapshot
                                ?? $service->duration_minutes,
                            'price_snapshot' => $service->pivot->price_snapshot
                                ?? $service->self_pay_amount,
                            'sort_order' => $index,
                            'quantity' => 1,
                        ];
                    }

                    $event->services()->sync($syncPayload);

                    $this->storeLegacyMapping(
                        branchId: $booking->branch_id,
                        legacyType: 'booking',
                        legacyId: $booking->id,
                        eventId: $event->id,
                    );

                    $stats['bookings_migrated']++;
                }
            });
    }

    private function migrateAvailabilityRules(?string $branchId, bool $dryRun, array &$stats): void
    {
        BookingAvailabilityRule::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->with('services')
            ->orderBy('id')
            ->chunkById(100, function ($rules) use ($dryRun, &$stats): void {
                foreach ($rules as $rule) {
                    $stats['rules_total']++;

                    if ($this->hasLegacyMapping('availability_rule', $rule->id)) {
                        $stats['skipped_existing_map']++;
                        continue;
                    }

                    if ($dryRun) {
                        $stats['rules_migrated']++;
                        continue;
                    }

                    $date = $rule->date ? Carbon::parse($rule->date) : now();
                    $startsAt = Carbon::parse($date->toDateString() . ' ' . $rule->starts_at);
                    $endsAt = Carbon::parse($date->toDateString() . ' ' . $rule->ends_at);

                    $event = Event::query()->create([
                        'branch_id' => $rule->branch_id,
                        'type' => EventType::AvailabilityRule,
                        'status' => $rule->is_enabled ? 'active' : 'cancelled',
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'timezone' => config('app.timezone'),
                        'title' => null,
                        'description' => null,
                        'recurrence_rule' => $this->toRecurrenceRule($rule),
                        'is_recurring' => (bool) $rule->repeats,
                        'metadata' => [
                            'legacy_rule_id' => $rule->id,
                            'excluded_dates' => $rule->excluded_dates ?? [],
                        ],
                    ]);

                    $event->availabilityRuleDetail()->create([
                        'capacity_rules' => [
                            'bookable_places' => (int) ($rule->bookable_places ?? 1),
                        ],
                        'visibility_rules' => [
                            'is_enabled' => (bool) $rule->is_enabled,
                        ],
                        'slot_interval_minutes' => 15,
                    ]);

                    $syncPayload = [];
                    foreach ($rule->services as $index => $service) {
                        $syncPayload[$service->id] = [
                            'duration_minutes_snapshot' => $service->duration_minutes,
                            'price_snapshot' => $service->self_pay_amount,
                            'sort_order' => $index,
                            'quantity' => 1,
                        ];
                    }

                    $event->services()->sync($syncPayload);

                    $this->storeLegacyMapping(
                        branchId: $rule->branch_id,
                        legacyType: 'availability_rule',
                        legacyId: $rule->id,
                        eventId: $event->id,
                    );

                    $stats['rules_migrated']++;
                }
            });
    }

    private function migrateCapacityWindows(?string $branchId, bool $dryRun, array &$stats): void
    {
        CapacityWindow::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->with(['service', 'bookings'])
            ->orderBy('id')
            ->chunkById(100, function ($windows) use ($dryRun, &$stats): void {
                foreach ($windows as $window) {
                    $stats['capacity_total']++;

                    if ($this->hasLegacyMapping('capacity_window', $window->id)) {
                        $stats['skipped_existing_map']++;
                        continue;
                    }

                    if ($dryRun) {
                        $stats['capacity_migrated']++;
                        continue;
                    }

                    $reserved = $window->bookings
                        ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                        ->count();

                    $event = Event::query()->create([
                        'branch_id' => $window->branch_id,
                        'type' => EventType::GroupEvent,
                        'status' => $window->status,
                        'starts_at' => $window->starts_at,
                        'ends_at' => $window->ends_at,
                        'timezone' => config('app.timezone'),
                        'title' => null,
                        'description' => null,
                        'recurrence_rule' => null,
                        'is_recurring' => false,
                        'metadata' => [
                            'series_uuid' => $window->series_uuid,
                            'legacy_capacity_window_id' => $window->id,
                        ],
                    ]);

                    $event->groupDetail()->create([
                        'service_id' => $window->service_id,
                        'service_name' => $window->service?->name,
                        'capacity' => (int) $window->capacity,
                        'reserved_places' => (int) $reserved,
                        'group_status' => $window->status,
                        'notes' => $window->admin_note,
                    ]);

                    if ($window->service_id) {
                        $event->services()->sync([
                            $window->service_id => [
                                'duration_minutes_snapshot' => $window->service?->duration_minutes,
                                'price_snapshot' => $window->service?->self_pay_amount,
                                'sort_order' => 0,
                                'quantity' => 1,
                            ],
                        ]);
                    }

                    foreach ($window->bookings as $booking) {
                        if (in_array($booking->status, ['cancelled', 'rejected', 'no_show'], true)) {
                            continue;
                        }

                        $event->participants()->create([
                            'patient_id' => null,
                            'status' => 'confirmed',
                            'booked_at' => $booking->created_at,
                            'notes' => $booking->patient_note,
                            'participant_name' => $booking->patient_name,
                            'participant_email' => $booking->patient_email,
                            'participant_phone' => $booking->patient_phone,
                        ]);
                    }

                    $this->storeLegacyMapping(
                        branchId: $window->branch_id,
                        legacyType: 'capacity_window',
                        legacyId: $window->id,
                        eventId: $event->id,
                    );

                    $stats['capacity_migrated']++;
                }
            });
    }

    private function toRecurrenceRule(BookingAvailabilityRule $rule): ?array
    {
        if (! $rule->repeats) {
            return null;
        }

        $frequency = match ($rule->repeat_unit) {
            'days' => 'daily',
            'months' => 'monthly',
            default => 'weekly',
        };

        return [
            'frequency' => $frequency,
            'interval' => max(1, (int) ($rule->repeat_every ?? 1)),
            'weekdays' => $rule->repeat_weekdays ?? [],
            'ends' => [
                'type' => filled($rule->repeat_ends_on) ? 'on' : 'never',
                'until' => $rule->repeat_ends_on?->toDateString(),
                'count' => null,
            ],
        ];
    }

    private function hasLegacyMapping(string $legacyType, int $legacyId): bool
    {
        return DB::table('calendar_legacy_event_maps')
            ->where('legacy_type', $legacyType)
            ->where('legacy_id', $legacyId)
            ->exists();
    }

    private function storeLegacyMapping(int $branchId, string $legacyType, int $legacyId, int $eventId): void
    {
        DB::table('calendar_legacy_event_maps')->insert([
            'branch_id' => $branchId,
            'legacy_type' => $legacyType,
            'legacy_id' => $legacyId,
            'event_id' => $eventId,
            'meta' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
