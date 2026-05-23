<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();

        return Inertia::render('Admin/Dashboard', [
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
                ->paginate(5),
        ]);
    }
}