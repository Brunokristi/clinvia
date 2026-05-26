<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ApiClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ApiClients/Index', [
            'apiClients' => ApiClient::query()
                ->with(['company:id,legal_name', 'domains'])
                ->latest()
                ->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ApiClients/Create', [
            'companies' => Company::query()
                ->select(['id', 'legal_name'])
                ->where('is_active', true)
                ->orderBy('legal_name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['required', 'boolean'],

            'domains' => ['nullable', 'array'],
            'domains.*.domain' => ['required', 'string', 'max:255'],
            'domains.*.is_active' => ['required', 'boolean'],
        ]);

        $plainToken = 'clinvia_' . Str::random(48);

        DB::transaction(function () use ($data, $plainToken) {
            $apiClient = ApiClient::create([
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'key_hash' => Hash::make($plainToken),
                'plain_text_token' => $plainToken,
                'rate_limit_per_minute' => $data['rate_limit_per_minute'],
                'is_active' => $data['is_active'],
            ]);

            foreach ($data['domains'] ?? [] as $domain) {
                $apiClient->domains()->create([
                    'domain' => rtrim($domain['domain'], '/'),
                    'is_active' => $domain['is_active'],
                ]);
            }
        });

        return redirect()
            ->back()
            ->with('success', 'API klient bol vytvorený.')
            ->with('api_token', $plainToken);
    }

    public function edit(ApiClient $apiClient): Response
    {
        return Inertia::render('Admin/ApiClients/Edit', [
            'apiClient' => $apiClient->load(['domains', 'company:id,legal_name']),
            'companies' => Company::query()
                ->select(['id', 'legal_name'])
                ->where('is_active', true)
                ->orderBy('legal_name')
                ->get(),
        ]);
    }

    public function update(Request $request, ApiClient $apiClient): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['required', 'boolean'],

            'domains' => ['nullable', 'array'],
            'domains.*.domain' => ['required', 'string', 'max:255'],
            'domains.*.is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($apiClient, $data) {
            $apiClient->update([
                'company_id' => $data['company_id'],
                'name' => $data['name'],
                'rate_limit_per_minute' => $data['rate_limit_per_minute'],
                'is_active' => $data['is_active'],
            ]);

            $apiClient->domains()->delete();

            foreach ($data['domains'] ?? [] as $domain) {
                $apiClient->domains()->create([
                    'domain' => rtrim($domain['domain'], '/'),
                    'is_active' => $domain['is_active'],
                ]);
            }
        });

        return redirect()
            ->route('companies.api-clients', $apiClient->company_id)
            ->with('success', 'API klient bol upravený.');
    }

    public function destroy(ApiClient $apiClient): RedirectResponse
    {
        $apiClient->delete();

        return redirect()
            ->route('companies.api-clients', $apiClient->company_id)
            ->with('success', 'API klient bol odstránený.');
    }

    public function regenerate(ApiClient $apiClient): RedirectResponse
    {
        $plainToken = 'clinvia_' . Str::random(48);

        $apiClient->update([
            'key_hash' => Hash::make($plainToken),
            'plain_text_token' => $plainToken,
            'last_used_at' => null,
        ]);

        return redirect()
            ->route('companies.api-clients', $apiClient->company_id)
            ->with('success', 'API token bol pregenerovaný.')
            ->with('api_token', $plainToken);
    }
}