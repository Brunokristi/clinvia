<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCompanyController extends Controller
{
    public function show(Request $request, Company $company): JsonResponse
    {
        $apiClient = $request->attributes->get('api_client');

        abort_if($apiClient->company_id !== $company->id, 403);
        abort_if(! $company->is_active, 404);

        $company->load([
            'branches' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name');
            },

            'branches.contacts' => function ($query) {
                $query->orderBy('sort_order');
            },

            'branches.openingHours' => function ($query) {
                $query->orderBy('day_of_week');
            },

            'branches.openingHours.intervals' => function ($query) {
                $query->orderBy('sort_order');
            },

            'branches.employees' => function ($query) {
                $query
                    ->where('employees.is_active', true)
                    ->orderBy('branch_employees.sort_order')
                    ->orderBy('employees.last_name')
                    ->orderBy('employees.first_name');
            },

            'branches.services' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },

            'branches.services.category',

            'branches.services.information' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },

            'branches.services.necessities' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },

            'branches.services.steps' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },

            'branches.services.tags' => function ($query) {
                $query->orderBy('sort_order');
            },

            'branches.services.files' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },
        ]);

        return response()->json([
            'data' => [
                'id' => $company->id,
                'slug' => $company->slug,
                'legal_name' => $company->legal_name,
                'email' => $company->email,
                'phone' => $company->phone,
                'website' => $company->website,
                'address_line_1' => $company->address_line_1,
                'address_line_2' => $company->address_line_2,
                'city' => $company->city,
                'postal_code' => $company->postal_code,
                'region' => $company->region,
                'country' => $company->country,

                'branches' => $company->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'slug' => $branch->slug,
                        'type' => $branch->type,
                        'description' => $branch->description,
                        'address_line_1' => $branch->address_line_1,
                        'address_line_2' => $branch->address_line_2,
                        'city' => $branch->city,
                        'postal_code' => $branch->postal_code,
                        'country' => $branch->country,
                        'latitude' => $branch->latitude,
                        'longitude' => $branch->longitude,
                        'website' => $branch->website,

                        'contacts' => $branch->contacts,
                        'opening_hours' => $branch->openingHours,
                        'employees' => $branch->employees,

                        'services' => $branch->services->map(function ($service) {
                            return [
                                'id' => $service->id,
                                'name' => $service->name,
                                'slug' => $service->slug,
                                'short_description' => $service->short_description,
                                'description' => $service->description,
                                'icon' => $service->icon,
                                'featured_image_path' => $service->featured_image_path,
                                'duration_sessions' => $service->duration_sessions,
                                'duration_minutes' => $service->duration_minutes,
                                'is_active' => $service->is_active,
                                'sort_order' => $service->sort_order,
                                'insurance_amount' => $service->insurance_amount,
                                'insurance_note' => $service->insurance_note,
                                'self_pay_amount' => $service->self_pay_amount,
                                'self_pay_note' => $service->self_pay_note,
                                'category' => $service->category,
                                'information' => $service->information,
                                'necessities' => $service->necessities,
                                'steps' => $service->steps,
                                'tags' => $service->tags,
                                'files' => $service->files,
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }
}