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
use Inertia\Inertia;
use Inertia\Response;

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
        $data['is_active'] = true;
        $data['sort_order'] = 0;

        $coordinates = $this->geocodeCoordinates($data);

        if ($coordinates) {
            $data['latitude'] = $coordinates['latitude'];
            $data['longitude'] = $coordinates['longitude'];
        }

        $branch = Branch::create($data);

        if (! empty($data['invite_email'])) {
            app(UserInvitationService::class)->sendBranchInvitation(
                $branch,
                $data['invite_email'],
                $request->user(),
            );
        }

        return redirect()
            ->route('branches.edit', $branch)
            ->with('success', 'Pobočka bola vytvorená' . (! empty($data['invite_email']) ? ' a pozvánka bola odoslaná.' : '.'));
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

    public function contacts(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        return Inertia::render('Admin/Branches/Contacts', [
            'branch' => $branch->load(['company:id,legal_name,slug', 'contacts']),
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
            'branch' => $branch->load(['company:id,legal_name,slug', 'services.category']),
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