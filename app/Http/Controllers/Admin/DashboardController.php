<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        $companies = Company::query()
            ->visibleTo($user)
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
            ->paginate(5);

        $companies->getCollection()->transform(function (Company $company) use ($user) {
            return [
                'id' => $company->id,
                'slug' => $company->slug,
                'legal_name' => $company->legal_name,
                'company_id_number' => $company->company_id_number,
                'email' => $company->email,
                'phone' => $company->phone,
                'address_line_1' => $company->address_line_1,
                'address_line_2' => $company->address_line_2,
                'city' => $company->city,
                'postal_code' => $company->postal_code,
                'region' => $company->region,
                'country' => $company->country,
                'is_active' => $company->is_active,
                'created_at' => $company->created_at,
                'can_manage' => $user->canManageCompany($company->id),
            ];
        });

        $branches = Branch::query()
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
            ])
            ->where(function ($query) use ($user) {
                if ($user->isSuperAdmin()) {
                    return;
                }

                $query->whereHas('users', function ($query) use ($user) {
                    $query->where('users.id', $user->id)
                        ->where('user_branches.is_active', true);
                })->orWhereHas('company.users', function ($query) use ($user) {
                    $query->where('users.id', $user->id)
                        ->where('user_companies.is_active', true);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(5);

        $branches->getCollection()->transform(function (Branch $branch) use ($user) {
            return [
                'id' => $branch->id,
                'company_id' => $branch->company_id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'type' => $branch->type,
                'city' => $branch->city,
                'is_active' => $branch->is_active,
                'sort_order' => $branch->sort_order,
                'created_at' => $branch->created_at,
                'company' => $branch->company,
                'can_manage' => $user->canAccessBranch($branch),
            ];
        });

        return Inertia::render('Admin/Dashboard', [
            'companies' => $companies,
            'branches' => $branches,
        ]);
    }
}