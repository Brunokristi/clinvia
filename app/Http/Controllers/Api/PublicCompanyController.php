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

            'branches.branchServices' => function ($query) {
                $query
                    ->where('is_available', true)
                    ->orderBy('sort_order');
            },

            'branches.branchServices.prices' => function ($query) {
                $query
                    ->where('is_visible', true)
                    ->orderBy('sort_order');
            },

            'branches.branchServices.service.category',

            'branches.branchServices.service.information' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },

            'branches.branchServices.service.necessities' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },

            'branches.branchServices.service.steps' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },

            'branches.branchServices.service.tags' => function ($query) {
                $query->orderBy('sort_order');
            },

            'branches.branchServices.service.files' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },
        ]);

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'legal_name' => $company->legal_name,
                'description' => $company->description,
                'email' => $company->email,
                'phone' => $company->phone,
                'website' => $company->website,

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

                        'services' => $branch->branchServices->map(function ($branchService) {
                            $service = $branchService->service;

                            return [
                                'id' => $branchService->id,
                                'service_id' => $service->id,
                                'title' => $branchService->custom_title ?: $service->name,
                                'custom_title' => $branchService->custom_title,
                                'custom_description' => $branchService->custom_description,
                                'is_available' => $branchService->is_available,
                                'sort_order' => $branchService->sort_order,
                                'prices' => $branchService->prices,

                                'service' => [
                                    'id' => $service->id,
                                    'category' => $service->category,
                                    'name' => $service->name,
                                    'slug' => $service->slug,
                                    'short_description' => $service->short_description,
                                    'description' => $service->description,
                                    'icon' => $service->icon,
                                    'featured_image_path' => $service->featured_image_path,
                                    'duration_minutes' => $service->duration_minutes,
                                    'information' => $service->information,
                                    'necessities' => $service->necessities,
                                    'steps' => $service->steps,
                                    'tags' => $service->tags,
                                    'files' => $service->files,
                                ],
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }
}