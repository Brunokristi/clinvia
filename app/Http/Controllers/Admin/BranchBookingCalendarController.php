<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Services\AdminBookingCalendarService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BranchBookingCalendarController extends Controller
{
    public function index(Request $request, Branch $branch, AdminBookingCalendarService $calendarService): Response
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $date = Carbon::parse($request->string('date', now()->toDateString()));
        $rangeStart = now()->copy()->subMonth()->startOfDay();
        $rangeEnd = now()->copy()->addMonths(6)->endOfDay();

        $branch->load([
            'company:id,legal_name,slug',
            'publicSite',
            'openingHours.intervals',
            'bookingAvailabilityRules.services',
            'bookingSlots.service',
            'branchInboxMessages' => function ($query) {
                $query->latest()->limit(15);
            },
        ]);

        return Inertia::render('Admin/Branches/Bookings', [
            'branch' => $branch,
            'services' => Service::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'availableRescheduleSlots' => $calendarService->getAvailableAdminSlots($branch),
            'calendarBookings' => $calendarService->getCalendarBookings($branch, $rangeStart, $rangeEnd),
            'calendarCapacityWindows' => $calendarService->getCalendarCapacityWindows($branch, $rangeStart, $rangeEnd),
            'todayBookingsCount' => Booking::query()
                ->where('branch_id', $branch->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->whereHas('bookingSlot', function ($query) use ($date) {
                    $query->whereDate('starts_at', $date);
                })
                ->count(),
            'unreadMessagesCount' => BranchInboxMessage::query()
                ->where('branch_id', $branch->id)
                ->whereNull('read_at')
                ->count(),
            'selectedDate' => $date->toDateString(),
        ]);
    }

    public function updateServices(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'services' => ['required', 'array'],
            'services.*.id' => ['required', 'integer', 'exists:services,id'],
            'services.*.is_bookable' => ['required', 'boolean'],
            'services.*.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'services.*.capacity' => ['nullable', 'integer', 'min:1'],
            'services.*.buffer_before_minutes' => ['nullable', 'integer', 'min:0'],
            'services.*.buffer_after_minutes' => ['nullable', 'integer', 'min:0'],
            'services.*.booking_type' => ['required', 'in:individual,group'],
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            foreach ($validated['services'] as $item) {
                Service::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($item['id'])
                    ->update([
                        'is_bookable' => $item['is_bookable'],
                        'duration_minutes' => $item['duration_minutes'] ?? null,
                        'capacity' => $item['capacity'] ?? 1,
                        'buffer_before_minutes' => $item['buffer_before_minutes'] ?? 0,
                        'buffer_after_minutes' => $item['buffer_after_minutes'] ?? 0,
                        'booking_type' => $item['booking_type'],
                    ]);
            }
        });

        return back()->with('success', 'Nastavenia služieb boli uložené.');
    }

    public function markMessageRead(Request $request, Branch $branch, BranchInboxMessage $message): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($message->branch_id !== $branch->id, 404);

        $message->update([
            'read_at' => now(),
        ]);

        return back();
    }
}
