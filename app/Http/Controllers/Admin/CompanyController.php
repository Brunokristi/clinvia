<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Models\UserCompany;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        return Inertia::render('Admin/Companies/Index', [
            'companies' => Company::query()
                ->accessibleTo($user)
                ->select([
                    'id',
                    'slug',
                    'legal_name',
                    'company_id_number',
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
        return Inertia::render('Admin/Companies/Create');
    }

    public function onboard(): Response
    {
        return Inertia::render('Admin/Companies/Onboard');
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
            'invite_email' => ['required', 'email', 'max:255'],
        ]);

        $slug = Str::slug($data['company_legal_name']);

        if (Company::where('slug', $slug)->exists()) {
            return back()->withErrors([
                'company_legal_name' => 'Takto vytvorený slug už existuje. Skús iný oficiálny názov.',
            ])->withInput();
        }

        $company = DB::transaction(function () use ($data, $slug) {
            $company = Company::create([
                ...$this->companyDataFromRequest($data),
                'slug' => $slug,
            ]);

            return $company;
        });

        app(UserInvitationService::class)->sendCompanyInvitation(
            $company,
            $data['invite_email'],
            $request->user(),
        );

        return redirect()
            ->route('dashboard', $company)
            ->with('success', 'Firma bola vytvorená a pozvánka bola odoslaná.');
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

        return Inertia::render('Admin/Companies/Edit', [
            'company' => $company,
        ]);
    }

    public function branches(Company $company): Response
    {
        $user = request()->user();

        abort_if(! $company->newQuery()->accessibleTo($user)->whereKey($company->id)->exists(), 403);

        return Inertia::render('Admin/Companies/Branches', [
            'company' => $company,
            'branches' => $company->branches()
                ->select(['id', 'company_id', 'name', 'slug', 'address_line_1', 'city', 'is_active', 'created_at'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function apiClients(Company $company): Response
    {
        $user = request()->user();

        abort_unless($user->isSuperAdmin(), 403);
        abort_if(! $company->newQuery()->accessibleTo($user)->whereKey($company->id)->exists(), 403);

        return Inertia::render('Admin/Companies/ApiClients', [
            'company' => $company,
            'apiClients' => $company->apiClients()
                ->select(['id', 'company_id', 'name', 'key_hash', 'plain_text_token', 'is_active', 'rate_limit_per_minute', 'last_used_at', 'created_at'])
                ->with('domains:id,api_client_id,domain,is_active')
                ->orderBy('name')
                ->get(),
            'canSeeApiKeys' => true,
        ]);
    }

    public function users(Company $company): Response
    {
        $user = request()->user();

        abort_if(! $company->newQuery()->accessibleTo($user)->whereKey($company->id)->exists(), 403);

        return Inertia::render('Admin/Companies/Users', [
            'company' => $company->load([
                'users:id,first_name,last_name,email,global_role,is_active',
                'companyInvitations.invitedBy:id,first_name,last_name,email',
            ]),
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
            ->route('companies.edit', $company)
            ->with('success', 'Firma bola upravená.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        abort_if(! request()->user()->canAccessCompany($company->id), 403);

        $companyUserIds = $company->users()
            ->pluck('users.id')
            ->all();

        DB::transaction(function () use ($company, $companyUserIds) {
            foreach ($companyUserIds as $userId) {
                $hasOtherCompany = DB::table('user_companies')
                    ->where('user_id', $userId)
                    ->where('company_id', '!=', $company->id)
                    ->exists();

                if (! $hasOtherCompany) {
                    DB::table('users')->where('id', $userId)->delete();
                }
            }

            $company->delete();
        });

        return redirect()
            ->route('dashboard')
            ->with('success', 'Firma bola odstránená.');
    }
}