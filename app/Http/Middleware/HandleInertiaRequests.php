<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $managedCompanies = [];

        if ($request->user()) {
            if ($request->user()->isSuperAdmin()) {
                $managedCompanies = Company::query()
                    ->select(['id', 'legal_name', 'slug'])
                    ->orderBy('legal_name')
                    ->get()
                    ->all();
            } else {
                $managedCompanies = $request->user()
                    ->companies()
                    ->select(['companies.id', 'companies.legal_name', 'companies.slug'])
                    ->wherePivot('is_active', true)
                    ->orderBy('companies.legal_name')
                    ->get()
                    ->all();
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'full_name' => $request->user()->full_name,
                    'email' => $request->user()->email,
                    'global_role' => $request->user()->global_role,
                    'is_active' => $request->user()->is_active,
                ] : null,
            ],
            'managedCompanies' => $managedCompanies,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'api_token' => fn () => $request->session()->get('api_token'),
            ],
        ];
    }
}
