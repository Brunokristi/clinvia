<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Company;
use App\Models\OpeningHour;
use App\Models\OpeningHourInterval;
use App\Models\Service;
use App\Models\User;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\EventMutationService;
use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalendarTestRecurring extends Command
{
    protected $signature = 'calendar:test-recurring
        {--branch= : Branch id to use for the smoke run}
        {--confirm : Confirm execution of recurring smoke checks}';

    protected $description = 'Run a focused recurring smoke flow with split/edit/delete checks and duplicate guards.';

    public function handle(RecurrenceExpansionService $expansionService, EventMutationService $mutationService): int
    {
        if (! $this->option('confirm')) {
            $this->error('Pass --confirm to execute recurring smoke checks.');

            return self::FAILURE;
        }

        $branch = $this->resolveBranch();

        if (! $branch) {
            $this->error('Unable to resolve or create branch for recurring smoke checks.');

            return self::FAILURE;
        }

        $checks = [];

        DB::beginTransaction();

        try {
            $actor = $this->resolveActor();
            $this->ensureOpeningHours($branch);
            $master = $this->createRecurringMaster($branch);

            $checks[] = $this->runStep('initial_render', fn () => $this->assertNoDuplicates(
                $this->snapshot($expansionService->forBranch($branch, $this->rangeStart(), $this->rangeEnd(), includeCancelled: true))
            ));

            $mutationService->update($master, [
                'occurrence_date' => '2026-07-13',
                'starts_at' => '2026-07-13 12:00:00',
                'ends_at' => '2026-07-13 13:00:00',
            ], $actor->id, 'this');

            $checks[] = $this->runStep('edit_this', fn () => $this->assertNoDuplicates(
                $this->snapshot($expansionService->forBranch($branch, $this->rangeStart(), $this->rangeEnd(), includeCancelled: true))
            ));

            $mutationService->update($master->fresh(), [
                'occurrence_date' => '2026-07-20',
                'starts_at' => '2026-07-20 12:00:00',
                'ends_at' => '2026-07-20 13:00:00',
            ], $actor->id, 'this_and_following');

            $checks[] = $this->runStep('edit_this_and_following', fn () => $this->assertNoDuplicates(
                $this->snapshot($expansionService->forBranch($branch, $this->rangeStart(), $this->rangeEnd(), includeCancelled: true))
            ));

            request()->replace(['occurrence_date' => '2026-07-06']);
            $mutationService->delete($master->fresh(), 'this');
            request()->replace([]);

            $checks[] = $this->runStep('delete_this', fn () => $this->assertNoDuplicates(
                $this->snapshot($expansionService->forBranch($branch, $this->rangeStart(), $this->rangeEnd(), includeCancelled: true))
            ));

            $this->table(['step', 'status', 'detail'], collect($checks)->map(fn (array $check) => [
                $check['step'],
                $check['ok'] ? 'PASS' : 'FAIL',
                $check['detail'],
            ])->all());

            $hasFailure = collect($checks)->contains(fn (array $check) => ! $check['ok']);

            DB::rollBack();

            if ($hasFailure) {
                $this->error('Recurring smoke flow failed.');

                return self::FAILURE;
            }

            $this->info('Recurring smoke flow passed.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function runStep(string $step, callable $stepRunner): array
    {
        try {
            $eventCount = (int) $stepRunner();

            return [
                'step' => $step,
                'ok' => true,
                'detail' => 'Rendered rows: ' . $eventCount,
            ];
        } catch (\Throwable $exception) {
            return [
                'step' => $step,
                'ok' => false,
                'detail' => $exception->getMessage(),
            ];
        }
    }

    private function assertNoDuplicates(array $snapshot): int
    {
        $displayKeys = array_column($snapshot, 'display_key');
        if (count($displayKeys) !== count(array_unique($displayKeys))) {
            throw new \RuntimeException('Duplicate display_key detected.');
        }

        $seriesOriginal = collect($snapshot)
            ->filter(fn (array $item) => $item['original_start_at'] !== null)
            ->map(fn (array $item) => $item['root_series_id'] . ':' . $item['original_start_at'])
            ->values()
            ->all();

        if (count($seriesOriginal) !== count(array_unique($seriesOriginal))) {
            throw new \RuntimeException('Duplicate root_series_id + original_start_at detected.');
        }

        return count($snapshot);
    }

    private function snapshot(Collection $events): array
    {
        return $events
            ->map(function (array $occurrence): array {
                /** @var Event $event */
                $event = $occurrence['event'];
                $rootSeriesId = (int) ($occurrence['root_event_id'] ?? $event->id);
                $original = ($occurrence['occurrence_original_starts_at'] ?? null)?->copy();

                $originalStart = $original
                    ? $original->setTimezone($event->timezone ?? config('app.timezone'))->format('Y-m-d H:i')
                    : null;

                return [
                    'display_key' => $originalStart !== null
                        ? $rootSeriesId . ':' . $originalStart
                        : 'single:' . $event->id,
                    'root_series_id' => $rootSeriesId,
                    'original_start_at' => $originalStart,
                ];
            })
            ->values()
            ->all();
    }

    private function createRecurringMaster(Branch $branch): Event
    {
        $master = Event::query()->create([
            'branch_id' => $branch->id,
            'type' => EventType::AvailabilityRule,
            'status' => 'confirmed',
            'title' => 'Recurring Smoke',
            'starts_at' => Carbon::parse('2026-07-06 10:00:00', 'Europe/Bratislava'),
            'ends_at' => Carbon::parse('2026-07-06 11:00:00', 'Europe/Bratislava'),
            'timezone' => 'Europe/Bratislava',
            'is_recurring' => true,
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => ['MO'],
                'ends' => [
                    'type' => 'after',
                    'count' => 4,
                    'until' => null,
                ],
            ],
            'metadata' => [
                'series_uuid' => (string) Str::uuid(),
                'smoke' => true,
            ],
        ]);

        $master->availabilityRuleDetail()->create([
            'slot_interval_minutes' => 15,
        ]);

        $service = Service::query()->where('branch_id', $branch->id)->first();
        if ($service) {
            $master->services()->sync([
                $service->id => [
                    'duration_minutes_snapshot' => $service->duration_minutes,
                    'price_snapshot' => $service->self_pay_amount,
                    'sort_order' => 0,
                    'quantity' => 1,
                ],
            ]);
        }

        return $master;
    }

    private function resolveBranch(): ?Branch
    {
        $branchId = $this->option('branch');

        if (filled($branchId)) {
            return Branch::query()->find((int) $branchId);
        }

        $company = Company::query()->create([
            'legal_name' => 'Recurring Smoke Company',
            'slug' => 'recurring-smoke-company-' . Str::random(8),
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Recurring Smoke Branch',
            'slug' => 'recurring-smoke-branch-' . Str::random(8),
            'type' => 'clinic',
            'is_active' => true,
            'booking_settings' => [
                'is_enabled' => true,
                'calendar_addon_enabled' => true,
                'booking_addon_enabled' => true,
            ],
        ]);

        Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Recurring Smoke Service',
            'slug' => 'recurring-smoke-service-' . Str::random(8),
            'is_bookable' => true,
            'duration_minutes' => 30,
            'capacity' => 5,
            'booking_type' => 'individual',
            'public_booking_type' => 'immediate_booking',
            'is_active' => true,
        ]);

        return $branch;
    }

    private function resolveActor(): User
    {
        $existing = User::query()->where('global_role', 'super_admin')->first();

        if ($existing) {
            return $existing;
        }

        return User::query()->create([
            'first_name' => 'Recurring',
            'last_name' => 'Smoke',
            'email' => 'recurring-smoke-' . Str::random(8) . '@example.com',
            'password' => 'password',
            'global_role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function ensureOpeningHours(Branch $branch): void
    {
        for ($day = 1; $day <= 7; $day++) {
            $openingHour = OpeningHour::query()->firstOrCreate([
                'branch_id' => $branch->id,
                'day_of_week' => $day,
            ], [
                'is_closed' => false,
                'sort_order' => $day,
            ]);

            $interval = OpeningHourInterval::query()
                ->where('opening_hour_id', $openingHour->id)
                ->first();

            if (! $interval) {
                OpeningHourInterval::query()->create([
                    'opening_hour_id' => $openingHour->id,
                    'opens_at' => '00:00',
                    'closes_at' => '23:59',
                    'sort_order' => 0,
                ]);
            }
        }
    }

    private function rangeStart(): Carbon
    {
        return Carbon::parse('2026-07-01 00:00:00', 'Europe/Bratislava');
    }

    private function rangeEnd(): Carbon
    {
        return Carbon::parse('2026-08-01 00:00:00', 'Europe/Bratislava');
    }
}
