<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchDisabledDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DisabledDayService
{
	public function __construct(
		private readonly \App\Modules\Calendar\Services\RecurrenceExpansionService $recurrenceExpansionService,
	) {
	}

	public function isDisabled(Branch $branch, Carbon|string $date): bool
	{
		if (! Schema::hasTable('branch_disabled_days')) {
			return false;
		}

		return BranchDisabledDay::query()
			->where('branch_id', $branch->id)
			->whereDate('date', $this->normalizeDate($date))
			->exists();
	}

	public function getDisabledDaysForRange(Branch $branch, Carbon $rangeStart, Carbon $rangeEnd): Collection
	{
		if (! Schema::hasTable('branch_disabled_days')) {
			return collect();
		}

		return BranchDisabledDay::query()
			->where('branch_id', $branch->id)
			->whereBetween('date', [
				$rangeStart->copy()->startOfDay(),
				$rangeEnd->copy()->endOfDay(),
			])
			->orderBy('date')
			->get();
	}

	public function eventCountOnDate(Branch $branch, Carbon|string $date): int
	{
		$normalizedDate = Carbon::parse($this->normalizeDate($date));

		return $this->recurrenceExpansionService->forBranch(
			$branch,
			$normalizedDate->copy()->startOfDay(),
			$normalizedDate->copy()->endOfDay(),
		)->count();
	}

	public function bookingCountOnDate(Branch $branch, Carbon|string $date): int
	{
		return $this->eventCountOnDate($branch, $date);
	}

	private function normalizeDate(Carbon|string $date): string
	{
		return $date instanceof Carbon
			? $date->toDateString()
			: Carbon::parse($date)->toDateString();
	}
}
