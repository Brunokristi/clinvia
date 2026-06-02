<?php

namespace App\Http\Controllers;

use App\Actions\CreateBookingAction;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Branch;
use App\Models\BranchInboxMessage;
use App\Models\Service;
use App\Services\BookingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicBranchSiteController extends Controller
{
    public function home(Branch $branch): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'contacts',
            'openingHours.intervals',
            'employees',
            'services.category',
        ]);

        return Inertia::render($this->templateView($branch, 'Home'), [
            'branch' => $this->branchData($branch),
            'featuredServices' => $branch->services
                ->where('is_active', true)
                ->take(4)
                ->values()
                ->map(fn ($service) => $this->serviceCardData($service)),
        ]);
    }

    public function services(Branch $branch): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'services.category',
        ]);

        return Inertia::render($this->templateView($branch, 'Services'), [
            'branch' => $this->branchData($branch),
            'services' => $branch->services
                ->where('is_active', true)
                ->values()
                ->map(fn ($service) => $this->serviceCardData($service)),
        ]);
    }

    public function service(Branch $branch, Service $service): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);
        $this->ensureServiceBelongsToBranch($branch, $service);

        $branch->load([
            'company',
            'publicSite',
        ]);

        $service->load([
            'category',
            'information',
            'necessities',
            'steps',
            'tags',
            'files',
        ]);

        return Inertia::render($this->templateView($branch, 'ServiceShow'), [
            'branch' => $this->branchData($branch),
            'service' => $this->serviceData($service),
        ]);
    }

    public function contact(Branch $branch): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'contacts',
            'openingHours.intervals',
            'employees',
        ]);

        return Inertia::render($this->templateView($branch, 'Contact'), [
            'branch' => $this->branchData($branch),
        ]);
    }

    public function booking(Request $request, Branch $branch, BookingAvailabilityService $availabilityService): Response
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $branch->load([
            'company',
            'publicSite',
            'contacts',
            'services.category',
        ]);

        $selectedDate = Carbon::parse($request->string('date', now()->toDateString()));
        $selectedServiceId = $request->integer('service');

        $selectedService = $selectedServiceId
            ? $branch->services->firstWhere('id', $selectedServiceId)
            : null;

        $availableSlots = $selectedService
            ? $this->filterSlotsForPatient(
                $availabilityService->getAvailableSlots($branch, $selectedService, $selectedDate)
            )
            : collect();

        return Inertia::render($this->templateView($branch, 'Booking'), [
            'branch' => $this->branchData($branch),
            'services' => $branch->services
                ->where('is_active', true)
                ->where('is_bookable', true)
                ->values()
                ->map(fn ($service) => $this->serviceCardData($service)),
            'selectedServiceId' => $selectedServiceId ?: null,
            'selectedDate' => $selectedDate->toDateString(),
            'availableSlots' => $availableSlots->map(fn ($slot) => [
                'id' => $slot->id,
                'starts_at' => $slot->starts_at->toDateTimeString(),
                'ends_at' => $slot->ends_at->toDateTimeString(),
                'capacity' => $slot->capacity,
                'confirmed_bookings_count' => $slot->confirmed_bookings_count,
                'is_enabled' => $slot->is_enabled,
            ])->values(),
            'selectedService' => $selectedService ? $this->serviceCardData($selectedService) : null,
        ]);
    }

    public function storeBooking(Request $request, Branch $branch, CreateBookingAction $createBookingAction): RedirectResponse
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $validated = $request->validate([
            'booking_slot_id' => ['required', 'integer', 'exists:booking_slots,id'],
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_note' => ['nullable', 'string'],
        ]);

        $slot = $branch->bookingSlots()
            ->with('service')
            ->whereKey($validated['booking_slot_id'])
            ->where('is_enabled', true)
            ->firstOrFail();

        $this->ensureSlotCanBeBooked($slot);

        $createBookingAction->execute($branch, $slot, $validated);

        return redirect()
            ->route('public.branch.booking', ['branch' => $branch->slug])
            ->with('success', 'Rezervácia bola prijatá. Skontrolujte si email s potvrdením.');
    }

    public function storeContactMessage(Request $request, Branch $branch): RedirectResponse
    {
        $this->ensurePublicSiteIsEnabled($branch);

        $validated = $request->validate([
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_email' => ['nullable', 'email', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        BranchInboxMessage::create([
            'branch_id' => $branch->id,
            'type' => 'contact_message',
            'title' => 'Nová správa z kontaktného formulára',
            'body' => $validated['body'],
            'sender_name' => $validated['sender_name'],
            'sender_email' => $validated['sender_email'] ?? null,
            'sender_phone' => $validated['sender_phone'] ?? null,
        ]);

        return back()->with('success', 'Správa bola odoslaná.');
    }

    private function filterSlotsForPatient(Collection $slots): Collection
    {
        return $slots
            ->loadMissing('service')
            ->filter(function (BookingSlot $slot) {
                return $this->slotCanBeShownToPatient($slot);
            })
            ->values();
    }

    private function ensureSlotCanBeBooked(BookingSlot $slot): void
    {
        if (! $this->slotCanBeShownToPatient($slot)) {
            throw ValidationException::withMessages([
                'booking_slot_id' => 'Tento termín už nie je dostupný.',
            ]);
        }
    }

    private function slotCanBeShownToPatient(BookingSlot $slot): bool
    {
        $slot->loadMissing('service');

        $service = $slot->service;

        if (! $service) {
            return false;
        }

        if (! $slot->is_enabled) {
            return false;
        }

        if ($slot->starts_at->isPast()) {
            return false;
        }

        $overlappingBookings = Booking::query()
            ->where('branch_id', $slot->branch_id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
            ->whereHas('bookingSlot', function ($query) use ($slot) {
                $query
                    ->where('starts_at', '<', $slot->ends_at)
                    ->where('ends_at', '>', $slot->starts_at);
            })
            ->get();

        if (($service->booking_type ?? 'individual') !== 'group') {
            return $overlappingBookings->isEmpty();
        }

        $blockingBookings = $overlappingBookings->filter(function (Booking $booking) use ($slot) {
            return (int) $booking->service_id !== (int) $slot->service_id
                || (int) $booking->booking_slot_id !== (int) $slot->id;
        });

        if ($blockingBookings->isNotEmpty()) {
            return false;
        }

        $sameSlotBookingsCount = $overlappingBookings
            ->filter(function (Booking $booking) use ($slot) {
                return (int) $booking->service_id === (int) $slot->service_id
                    && (int) $booking->booking_slot_id === (int) $slot->id;
            })
            ->count();

        $capacity = max(1, (int) ($slot->capacity ?? $service->capacity ?? 1));

        return $sameSlotBookingsCount < $capacity;
    }

    private function ensurePublicSiteIsEnabled(Branch $branch): void
    {
        $branch->loadMissing('publicSite');

        abort_unless($branch->publicSite?->is_enabled, 404);
    }

    private function ensureServiceBelongsToBranch(Branch $branch, Service $service): void
    {
        abort_unless(
            $branch->services()->whereKey($service->id)->exists(),
            404
        );
    }

    private function templateView(Branch $branch, string $page): string
    {
        $branch->loadMissing('publicSite');

        $template = $branch->publicSite?->template ?? 'default';

        return "PublicBranch/Templates/{$template}/{$page}";
    }

    private function branchData(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'type' => $branch->type,
            'description' => $branch->description,
            'website' => $branch->website,
            'address' => [
                'line_1' => $branch->address_line_1,
                'line_2' => $branch->address_line_2,
                'city' => $branch->city,
                'postal_code' => $branch->postal_code,
                'country' => $branch->country,
            ],
            'location' => [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
            ],
            'company' => $branch->company ? [
                'id' => $branch->company->id,
                'name' => $branch->company->legal_name,
                'slug' => $branch->company->slug,
                'ico' => $branch->company->company_id_number,
                'dic' => $branch->company->tax_id,
                'ic_dph' => $branch->company->vat_id,
                'company_id_number' => $branch->company->company_id_number,
                'tax_id' => $branch->company->tax_id,
                'vat_id' => $branch->company->vat_id,
                'email' => $branch->company->email,
                'phone' => $branch->company->phone,
                'website' => $branch->company->website,
            ] : null,
            'public_site' => $branch->publicSite ? [
                'template' => $branch->publicSite->template,
                'primary_color' => $branch->publicSite->primary_color,
                'secondary_color' => $branch->publicSite->secondary_color,
                'logo_path' => $branch->publicSite->logo_path,
                'meta_title' => $branch->publicSite->meta_title,
                'meta_description' => $branch->publicSite->meta_description,
                'faq_items' => $branch->publicSite->faq_items ?? [],
            ] : null,
            'contacts' => $branch->contacts->map(fn ($contact) => [
                'type' => $contact->type,
                'label' => $contact->label,
                'value' => $contact->value,
                'is_primary' => $contact->is_primary,
            ])->values(),
            'opening_hours' => $branch->openingHours->map(fn ($openingHour) => [
                'day_of_week' => $openingHour->day_of_week,
                'is_closed' => $openingHour->is_closed,
                'note' => $openingHour->note,
                'intervals' => $openingHour->intervals->map(fn ($interval) => [
                    'opens_at' => $interval->opens_at,
                    'closes_at' => $interval->closes_at,
                ])->values(),
            ])->values(),
            'employees' => $branch->employees->map(fn ($employee) => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'title_before' => $employee->title_before,
                'title_after' => $employee->title_after,
                'position' => $employee->position,
                'bio' => $employee->bio,
                'photo_url' => $employee->photo_url,
                'email' => $employee->email,
                'phone' => $employee->phone,
            ])->values(),
        ];
    }

    private function serviceCardData(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'short_description' => $service->short_description,
            'description' => $service->description,
            'icon' => $service->icon,
            'duration_sessions' => $service->duration_sessions,
            'duration_minutes' => $service->duration_minutes,
            'insurance_amount' => $service->insurance_amount,
            'self_pay_amount' => $service->self_pay_amount,
            'category' => $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
                'slug' => $service->category->slug,
            ] : null,
        ];
    }

    private function serviceData(Service $service): array
    {
        return [
            ...$this->serviceCardData($service),
            'description' => $service->description,
            'insurance_note' => $service->insurance_note,
            'self_pay_note' => $service->self_pay_note,
            'information' => $service->information->map(fn ($item) => [
                'text' => $item->text,
            ])->values(),
            'necessities' => $service->necessities->map(fn ($item) => [
                'text' => $item->text,
            ])->values(),
            'steps' => $service->steps->map(fn ($step) => [
                'number' => $step->number,
                'title' => $step->title,
                'text' => $step->text,
            ])->values(),
            'tags' => $service->tags->map(fn ($tag) => [
                'name' => $tag->name,
            ])->values(),
            'files' => $service->files->map(fn ($file) => [
                'label' => $file->label,
                'original_name' => $file->original_name,
                'file_path' => $file->file_path,
            ])->values(),
        ];
    }
}