<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\Employee;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        abort_if(! request()->user()->canAccessBranch($branch), 403);
        $user = request()->user();

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
            'companies' => $companiesQuery->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('branches', 'slug')->where('company_id', $request->integer('company_id')),
            ],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],

            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (! $request->user()->canAccessCompany((int) $data['company_id'])) {
            abort(403);
        }

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        Branch::create($data);

        return redirect()
            ->route('branches.index')
            ->with('success', 'Pobočka bola vytvorená.');
    }

    public function edit(Branch $branch): Response
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        $user = request()->user();

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

        return Inertia::render('Admin/Branches/Edit', [
            'branch' => $branch->load([
                'contacts',
                'openingHours.intervals',
                'users:id,first_name,last_name,email,global_role,is_active',
                'employees',
                'branchServices.service.category',
                'branchServices.prices',
            ]),
            'companies' => $companiesQuery->get(),
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
            'availableServices' => Service::query()
                ->select([
                    'id',
                    'company_id',
                    'category_id',
                    'name',
                    'slug',
                    'short_description',
                    'duration_minutes',
                    'is_active',
                ])
                ->with('category:id,name')
                ->where('company_id', $branch->company_id)
                ->where('is_active', true)
                ->whereDoesntHave('branches', function ($query) use ($branch) {
                    $query->where('branches.id', $branch->id);
                })
                ->orderBy('name')
                ->get(),
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
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'slug')
                    ->where('company_id', $request->integer('company_id'))
                    ->ignore($branch->id),
            ],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],

            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],

            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (! $request->user()->canAccessCompany((int) $data['company_id'])) {
            abort(403);
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;

        $branch->update($data);

        return redirect()
            ->route('branches.index')
            ->with('success', 'Pobočka bola upravená.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        abort_if(! request()->user()->canAccessBranch($branch), 403);

        $branch->delete();

        return redirect()
            ->route('branches.index')
            ->with('success', 'Pobočka bola odstránená.');
    }
}