<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Service;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingAvailabilityService
{
    public function getAvailableSlots(Branch $branch, Service $service, Carbon $date): Collection
    {
        return $this->getAvailableSlotsForServices(
            branch: $branch,
            services: collect([$service]),
            date: $date,
        );
    }

    public function getAvailableSlotsForServices(Branch $branch, Collection $services, Carbon $date): Collection
    {
        if (app(DisabledDayService::class)->isDisabled($branch, $date)) {
            return collect();
        }

        $services = $services
            ->filter(fn (Service $service) => $service->is_bookable)
            ->values();

        if ($services->isEmpty()) {
            return collect();
        }

        /*
         * Direct booking into a capacity window is allowed only when the patient
         * selected exactly one service.
         *
         * A capacity window belongs to one concrete service. If the patient selects
         * multiple services, we must not pretend that one capacity window covers all
         * selected services. Multi-service selection should go through rules/request
         * logic, or fall back to a general day request.
         */
        if ($services->count() !== 1) {
            return collect();
        }

        $service = $services->first();

        /*
         * Important:
         * The public booking page does not ask the patient for a date before showing
         * direct capacity-window options. So we search from the selected/default date
         * forward, not only inside that one day.
         */
        $from = $date->copy()->startOfDay();

        if ($from->isPast()) {
            $from = now();
        }

        $to = $from->copy()->addDays(90)->endOfDay();

        return Event::query()
            ->with(['services', 'groupDetail'])
            ->where('branch_id', $branch->id)
            ->where('type', EventType::GroupEvent)
            ->whereHas('services', fn ($query) => $query->where('services.id', $service->id))
            ->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->whereHas('services', function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('is_bookable', true);
            })
            ->orderBy('starts_at')
            ->limit(30)
            ->get()
            ->filter(fn (Event $event) => $this->isCapacityWindowAvailable($event))
            ->values();
    }

    public function isCapacityWindowAvailable(Event $event): bool
    {
        $capacity = (int) ($event->groupDetail?->capacity ?? 0);
        $reserved = (int) ($event->groupDetail?->reserved_places ?? 0);

        return $event->status !== 'cancelled'
            && $event->starts_at?->isFuture()
            && $reserved < $capacity;
    }
}