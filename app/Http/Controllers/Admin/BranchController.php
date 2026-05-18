<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
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
        return Inertia::render('Admin/Branches/Index', [
            'branches' => Branch::query()
                ->with('company:id,name')
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
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Branches/Create', [
            'companies' => Company::query()
                ->select(['id', 'name'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        Branch::create($data);

        return redirect()
            ->route('branches.index')
            ->with('success', 'Pobočka bola vytvorená.');
    }

    public function edit(Branch $branch): Response
    {
        return Inertia::render('Admin/Branches/Edit', [
            'branch' => $branch->load([
                'contacts',
            ]),
            'companies' => Company::query()
                ->select(['id', 'name'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
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

        $data['sort_order'] = $data['sort_order'] ?? 0;

        $branch->update($data);

        return redirect()
            ->route('branches.index')
            ->with('success', 'Pobočka bola upravená.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return redirect()
            ->route('branches.index')
            ->with('success', 'Pobočka bola odstránená.');
    }
}