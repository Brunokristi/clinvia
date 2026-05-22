<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        return Inertia::render('Companies/Index', [
            'companies' => Company::query()
                ->accessibleTo($user)
                ->select([
                    'id',
                    'slug',
                    'legal_name',
                    'email',
                    'phone',
                    'address_line_1',
                    'address_line_2',
                    'city',
                    'postal_code',
                    'region',
                    'country',
                    'is_active',
                    'created_at',
                ])
                ->orderBy('legal_name')
                ->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Companies/Create');
    }

    public function onboard(): Response
    {
        return Inertia::render('Companies/Onboard');
    }

    private function companyDataFromRequest(array $data): array
    {
        return [
            'legal_name' => $data['company_legal_name'],
            'slug' => Str::slug($data['company_legal_name']),
            'company_id_number' => $data['company_id_number'] ?? null,
            'tax_id' => $data['company_tax_id'] ?? null,
            'vat_id' => $data['company_vat_id'] ?? null,
            'address_line_1' => $data['company_address_line_1'] ?? null,
            'address_line_2' => $data['company_address_line_2'] ?? null,
            'city' => $data['company_city'] ?? null,
            'postal_code' => $data['company_postal_code'] ?? null,
            'region' => $data['company_region'] ?? null,
            'country' => $data['company_country'] ?? null,
            'email' => $data['company_email'] ?? null,
            'phone' => $data['company_phone'] ?? null,
            'website' => $data['company_website'] ?? null,
            'is_active' => $data['company_is_active'],
            'sort_order' => 0,
        ];
    }

    public function storeOnboard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_legal_name' => ['required', 'string', 'max:255'],
            'company_id_number' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'company_vat_id' => ['nullable', 'string', 'max:255'],
            'company_address_line_1' => ['nullable', 'string', 'max:255'],
            'company_address_line_2' => ['nullable', 'string', 'max:255'],
            'company_city' => ['nullable', 'string', 'max:255'],
            'company_postal_code' => ['nullable', 'string', 'max:255'],
            'company_region' => ['nullable', 'string', 'max:255'],
            'company_country' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'company_website' => ['nullable', 'string', 'max:255'],
            'company_is_active' => ['required', 'boolean'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_first_name' => ['nullable', 'string', 'max:255'],
            'admin_last_name' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $existingAdmin = User::where('email', $data['admin_email'])->first();

        if (! $existingAdmin) {
            Validator::make($request->all(), [
                'admin_first_name' => ['required', 'string', 'max:255'],
                'admin_last_name' => ['required', 'string', 'max:255'],
                'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            ])->validate();
        }

        $slug = Str::slug($data['company_legal_name']);

        if (Company::where('slug', $slug)->exists()) {
            return back()->withErrors([
                'company_legal_name' => 'Takto vytvorený slug už existuje. Skús iný oficiálny názov.',
            ])->withInput();
        }

        $company = DB::transaction(function () use ($data, $slug, $existingAdmin) {
            $company = Company::create([
                ...$this->companyDataFromRequest($data),
                'slug' => $slug,
            ]);

            $adminUser = $existingAdmin;

            if (! $adminUser) {
                $adminUser = User::create([
                    'first_name' => $data['admin_first_name'],
                    'last_name' => $data['admin_last_name'],
                    'email' => $data['admin_email'],
                    'password' => Hash::make($data['admin_password']),
                    'global_role' => 'admin',
                    'is_active' => true,
                ]);
            }

            UserCompany::firstOrCreate([
                'user_id' => $adminUser->id,
                'company_id' => $company->id,
            ], [
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            return $company;
        });

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', 'Firma a prvý administrátor boli vytvorení.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'company_id_number' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['slug'] = Str::slug($data['legal_name']);

        if (Company::where('slug', $data['slug'])->exists()) {
            return back()->withErrors([
                'legal_name' => 'Takto vytvorený slug už existuje. Skús iný oficiálny názov.',
            ])->withInput();
        }

        Company::create($data);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Firma bola vytvorená.');
    }

    public function edit(Company $company): Response
    {
        $user = request()->user();

        abort_if(! $company->newQuery()->accessibleTo($user)->whereKey($company->id)->exists(), 403);

        return Inertia::render('Companies/Edit', [
            'company' => $company,
            'branches' => $company->branches()
                ->select(['id', 'company_id', 'name', 'slug', 'city', 'is_active', 'created_at'])
                ->orderBy('name')
                ->get(),
            'apiClients' => $user->isSuperAdmin()
                ? $company->apiClients()
                    ->select(['id', 'company_id', 'name', 'is_active', 'rate_limit_per_minute', 'last_used_at', 'created_at'])
                    ->with('domains:id,api_client_id,domain,is_active')
                    ->orderBy('name')
                    ->get()
                : [],
            'canSeeApiKeys' => $user->isSuperAdmin(),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($company->id), 403);

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'company_id_number' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['slug'] = Str::slug($data['legal_name']);
        $data['sort_order'] = 0;

        if (Company::where('slug', $data['slug'])->where('id', '!=', $company->id)->exists()) {
            return back()->withErrors([
                'legal_name' => 'Takto vytvorený slug už existuje. Skús iný oficiálny názov.',
            ])->withInput();
        }

        $company->update($data);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Firma bola upravená.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        abort_if(! request()->user()->canAccessCompany($company->id), 403);

        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', 'Firma bola odstránená.');
    }
}