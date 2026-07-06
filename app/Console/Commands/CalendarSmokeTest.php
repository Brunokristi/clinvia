<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Services\RecurrenceExpansionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalendarSmokeTest extends Command
{
    protected $signature = 'calendar:smoke-test
        {--branch= : Branch id}
        {--confirm : Confirm execution of branch smoke checks}';

    protected $description = 'Run low-cost integrity checks against unified calendar data for a branch.';

    public function handle(RecurrenceExpansionService $expansionService): int
    {
        if (! $this->option('confirm')) {
            $this->error('Pass --confirm to execute smoke checks.');

            return self::FAILURE;
        }

        $branchId = $this->option('branch');

        if (! filled($branchId)) {
            $this->error('The --branch option is required.');

            return self::FAILURE;
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            $this->error('Branch not found.');

            return self::FAILURE;
        }

        $rangeStart = now()->startOfDay();
        $rangeEnd = now()->addDays(30)->endOfDay();
        $events = Event::query()
            ->with(['groupDetail', 'participants'])
            ->where('branch_id', $branch->id)
            ->get();

        $checks = collect();

        $checks->push([
            'check' => 'time_ranges',
            'ok' => ! $events->contains(fn (Event $event) => $event->starts_at && $event->ends_at && $event->ends_at->lte($event->starts_at)),
            'detail' => 'All dated events end after they start.',
        ]);

        $checks->push([
            'check' => 'recurring_overrides_have_parent',
            'ok' => ! $events->contains(fn (Event $event) => $event->recurrence_parent_id !== null && ! Event::query()->whereKey($event->recurrence_parent_id)->exists()),
            'detail' => 'Recurring overrides reference an existing root event.',
        ]);

        $checks->push([
            'check' => 'group_reserved_places_match_confirmed_participants',
            'ok' => ! $events->contains(function (Event $event): bool {
                if (! $event->groupDetail) {
                    return false;
                }

                return (int) $event->groupDetail->reserved_places !== $event->participants->where('status', 'confirmed')->count();
            }),
            'detail' => 'Group event counters match confirmed participants.',
        ]);

        try {
            $expanded = $expansionService->forBranch($branch, Carbon::parse($rangeStart), Carbon::parse($rangeEnd), includeCancelled: true);
            $checks->push([
                'check' => 'expansion_runs',
                'ok' => true,
                'detail' => sprintf('Expansion succeeded for %d occurrence rows.', $expanded->count()),
            ]);
        } catch (\Throwable $exception) {
            $checks->push([
                'check' => 'expansion_runs',
                'ok' => false,
                'detail' => $exception->getMessage(),
            ]);
        }

        $this->table(['check', 'status', 'detail'], $checks->map(fn (array $check) => [
            $check['check'],
            $check['ok'] ? 'PASS' : 'FAIL',
            $check['detail'],
        ])->all());

        if ($checks->contains(fn (array $check) => ! $check['ok'])) {
            $this->error('Smoke test failed.');

            return self::FAILURE;
        }

        $this->info('Smoke test passed.');

        return self::SUCCESS;
    }
}