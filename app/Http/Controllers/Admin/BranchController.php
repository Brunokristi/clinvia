<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\Employee;
use App\Models\Category;
use App\Models\Service;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        $query = Branch::query()
            ->with('company:id,legal_name')
            ->select([
                'id',
                'company_id',
                'name',
                'slug',
                'type',
                'city',
                'is_active',
                'sort_order',
                'created_at',
            ]);

        if (! $user->isSuperAdmin()) {
            $companyIds = $user->companies()
                ->wherePivot('is_active', true)
                ->pluck('companies.id');

            $branchIds = $user->branches()
                ->wherePivot('is_active', true)
                ->pluck('branches.id');

            $query->where(function ($query) use ($companyIds, $branchIds) {
                $query
                    ->whereIn('company_id', $companyIds)
                    ->orWhereIn('id', $branchIds);
            });
        }

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $query
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();

        $selectedCompany = null;

        if (request()->filled('company')) {
            $companyId = request()->integer('company');

            abort_if(! Company::query()->accessibleTo($user)->whereKey($companyId)->exists(), 403);

            $selectedCompany = Company::query()
                ->select(['id', 'legal_name'])
                ->findOrFail($companyId);
        }

        $companiesQuery = Company::query()
            ->select(['id', 'legal_name'])
            ->where('is_active', true)
            ->orderBy('legal_name');

        if (! $user->isSuperAdmin()) {
            $companyIds = $user->companies()
                ->wherePivot('is_active', true)
                ->pluck('companies.id');

            $companiesQuery->whereIn('id', $companyIds);
        }

        return Inertia::render('Admin/Branches/Create', [
            'company' => $selectedCompany,
            'companies' => $companiesQuery->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            'website' => ['nullable', 'string', 'max:255'],
            'invite_email' => ['nullable', 'email', 'max:255'],
        ]);

        if (! $request->user()->canAccessCompany((int) $data['company_id'])) {
            abort(403);
        }

        $data['slug'] = Str::slug($data['name']);

        $branchExists = Branch::query()
            ->where('company_id', $data['company_id'])
            ->where('slug', $data['slug'])
            ->exists();

        if ($branchExists) {
            throw ValidationException::withMessages([
                'name' => 'Pobočka s týmto názvom už v tejto firme existuje.',
            ]);
        }

        $data['is_active'] = true;
        $data['sort_order'] = 0;

        $coordinates = $this->geocodeCoordinates($data);

        if ($coordinates) {
            $data['latitude'] = $coordinates['latitude'];
            $data['longitude'] = $coordinates['longitude'];
        }

        $branch = Branch::create($data);

        $invitationSent = false;

        if (! empty($data['invite_email'])) {
            try {
                app(UserInvitationService::class)->sendBranchInvitation(
                    $branch,
                    $data['invite_email'],
                    $request->user(),
                );

                $invitationSent = true;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()
            ->route('branches.booking.dashboard.page', ['branch' => $branch->id])
            ->with(
                'success',
                'Pobočka bola vytvorená' . ($invitationSent ? ' a pozvánka bola odoslaná.' : '.')
            );
    }

    public function edit(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        return Inertia::render('Admin/Branches/Edit', [
            'company' => $branch->company()->select(['id', 'legal_name'])->first(),
            'branch' => $branch->load([
                'company:id,legal_name,slug',
                'company',
            ]),
        ]);
    }

    public function settings(Request $request, Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        $routeName = $request->route()->getName();
        $tabName = match ($routeName) {
            'branches.contacts.page' => 'contacts',
            'branches.patients.page' => 'patients',
            'branches.opening-hours.page' => 'openingHours',
            'branches.employees.page' => 'employees',
            'branches.services.page' => 'services',
            'branches.users.page' => 'users',
            'branches.public-site.edit' => 'publicSite',
            'branches.public-site.page' => 'publicSite',
            default => $request->string('tab', 'info'),
        };

        $branch->load([
            'company:id,legal_name,slug',
            'company.users:id,first_name,last_name,email,global_role,is_active',
            'contacts',
            'patients',
            'openingHours.intervals',
            'employees',
            'services.category',
            'services.information',
            'services.steps',
            'services.files',
            'users:id,first_name,last_name,email,global_role,is_active',
            'branchInvitations.invitedBy:id,first_name,last_name,email',
            'publicSite',
        ])->loadCount(['contacts', 'patients', 'openingHours', 'employees', 'services', 'users']);

        $availableUsers = User::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'global_role', 'is_active'])
            ->whereIn('global_role', ['admin', 'editor', 'viewer'])
            ->where('is_active', true)
            ->whereDoesntHave('branches', function ($query) use ($branch) {
                $query->where('branches.id', $branch->id);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $categories = Category::query()
            ->select(['id', 'name'])
            ->where('company_id', $branch->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Branches/Settings', [
            'branch' => $branch,
            'availableUsers' => $availableUsers,
            'categories' => $categories,
            'insuranceCompanies' => $this->insuranceCompanies(),
            'templates' => [
                [
                    'label' => 'Predvolený',
                    'value' => 'default',
                ],
            ],
            'activeTab' => $tabName,
        ]);
    }

    public function contacts(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        return Inertia::render('Admin/Branches/Contacts', [
            'branch' => $branch->load(['company:id,legal_name,slug', 'contacts', 'publicSite']),
        ]);
    }

    public function openingHours(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        return Inertia::render('Admin/Branches/OpeningHours', [
            'branch' => $branch->load(['company:id,legal_name,slug', 'openingHours.intervals']),
        ]);
    }

    public function users(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        return Inertia::render('Admin/Branches/Users', [
            'branch' => $branch->load([
                'company:id,legal_name,slug',
                'company.users:id,first_name,last_name,email,global_role,is_active',
                'users:id,first_name,last_name,email,global_role,is_active',
                'branchInvitations.invitedBy:id,first_name,last_name,email',
            ]),
            'availableUsers' => User::query()
                ->select(['id', 'first_name', 'last_name', 'email', 'global_role', 'is_active'])
                ->whereIn('global_role', ['admin', 'editor', 'viewer'])
                ->where('is_active', true)
                ->whereDoesntHave('branches', function ($query) use ($branch) {
                    $query->where('branches.id', $branch->id);
                })
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function employees(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        return Inertia::render('Admin/Branches/Employees', [
            'branch' => $branch->load(['company:id,legal_name,slug', 'employees']),
            'availableEmployees' => Employee::query()
                ->select([
                    'id',
                    'company_id',
                    'first_name',
                    'last_name',
                    'title_before',
                    'title_after',
                    'position',
                    'email',
                    'phone',
                    'is_active',
                    'photo_path',
                ])
                ->where('company_id', $branch->company_id)
                ->where('is_active', true)
                ->whereDoesntHave('branches', function ($query) use ($branch) {
                    $query->where('branches.id', $branch->id);
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function services(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        return Inertia::render('Admin/Branches/Services', [
            'branch' => $branch->load(['company:id,legal_name,slug', 'services.category', 'services.information', 'services.steps', 'services.files']),
            'categories' => Category::query()
                ->select(['id', 'name'])
                ->where('company_id', $branch->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $request->user()->canAccessCompany((int) $data['company_id'])) {
            abort(403);
        }

        $data['slug'] = Str::slug($data['name']);
        $data['sort_order'] = $branch->sort_order;

        $coordinates = $this->geocodeCoordinates($data);

        if ($coordinates) {
            $data['latitude'] = $coordinates['latitude'];
            $data['longitude'] = $coordinates['longitude'];
        }

        $branch->update($data);

        return redirect()
            ->route('branches.edit', $branch)
            ->with('success', 'Pobočka bola upravená.');
    }

    public function updateSettings(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $validated = $request->validate([
            'public_site' => ['nullable', 'array'],
            'public_site.is_enabled' => ['boolean'],
            'public_site.template' => ['required_if:public_site.is_enabled,1', 'string', 'in:default'],
            'public_site.custom_domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('branch_public_sites', 'custom_domain')
                    ->ignore($branch->publicSite?->id),
            ],
            'public_site.primary_color' => ['nullable', 'string', 'max:20'],
            'public_site.secondary_color' => ['nullable', 'string', 'max:20'],
            'public_site.logo_path' => ['nullable', 'string', 'max:255'],
            'public_site.meta_title' => ['nullable', 'string', 'max:255'],
            'public_site.meta_description' => ['nullable', 'string'],

            'booking' => ['nullable', 'array'],
            'booking.is_enabled' => ['boolean'],
            'booking.allow_service_selection' => ['boolean'],
            'booking.allow_appointment_requests' => ['boolean'],
            'booking.booking_mode' => ['nullable', 'string', 'in:requests_only,verified_patients_direct,verified_patients_only,admin_only'],
            'booking.intro_text' => ['nullable', 'string', 'max:2000'],
            'booking.success_message' => ['nullable', 'string', 'max:2000'],

            'notifications' => ['nullable', 'array'],
            'notifications.is_enabled' => ['boolean'],
            'notifications.notification_emails' => ['nullable', 'array'],
            'notifications.notification_emails.*' => ['nullable', 'email', 'max:255'],
            'notifications.notify_new_appointment_request' => ['boolean'],
            'notifications.notify_new_booking' => ['boolean'],
            'notifications.notify_new_contact_form' => ['boolean'],

            'contracted_insurance_companies' => ['nullable', 'array'],
            'contracted_insurance_companies.*' => ['required', 'string', Rule::in(array_keys($this->insuranceCompanies()))],
            'show_other_branches_in_footer' => ['boolean'],
        ]);

        if (! empty($validated['public_site'])) {
            $branch->publicSite()->updateOrCreate(
                ['branch_id' => $branch->id],
                array_filter($validated['public_site'], fn ($value) => $value !== null),
            );
        }

        $validated['notifications'] = $validated['notifications'] ?? [];

        if (! ($validated['booking']['is_enabled'] ?? false)) {
            $validated['notifications']['notify_new_appointment_request'] = false;
            $validated['notifications']['notify_new_booking'] = false;
        }

        if (($validated['booking']['booking_mode'] ?? null) === 'verified_patients_only') {
            $validated['booking']['booking_mode'] = 'verified_patients_direct';
        }

        $branch->update([
            'booking_settings' => $validated['booking'] ?? [],
            'notification_settings' => $validated['notifications'] ?? [],
            'contracted_insurance_companies' => collect($validated['contracted_insurance_companies'] ?? [])
                ->map(fn (string $key): string => trim($key))
                ->filter(fn (string $key): bool => $key !== '')
                ->unique()
                ->values()
                ->all(),
            'show_other_branches_in_footer' => (bool) ($validated['show_other_branches_in_footer'] ?? false),
        ]);

        return back()->with('success', 'Nastavenia boli uložené.');
    }

    /**
     * @return array<string, array{label: string, full_name: string}>
     */
    private function insuranceCompanies(): array
    {
        return config('health_insurance.companies', []);
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        $branch->delete();

        return redirect()
            ->route('companies.branches', $branch->company_id)
            ->with('success', 'Pobočka bola odstránená.');
    }

    private function geocodeCoordinates(array $data): ?array
    {
        $parts = array_filter([
            $data['address_line_1'] ?? null,
            $data['address_line_2'] ?? null,
            $data['postal_code'] ?? null,
            $data['region'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? null,
        ]);

        if ($parts === []) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Clinvia/1.0 (+https://clinvia.local)',
            ])->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'jsonv2',
                'limit' => 1,
                'q' => implode(', ', $parts),
            ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json()[0] ?? null;

            if (! $result || ! isset($result['lat'], $result['lon'])) {
                return null;
            }

            return [
                'latitude' => $result['lat'],
                'longitude' => $result['lon'],
            ];
        } catch (\Throwable $throwable) {
            return null;
        }
    }
}