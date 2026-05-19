<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchServiceController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'create_new' => ['required', 'boolean'],
            'service_id' => ['nullable', 'required_if:create_new,false', 'exists:services,id'],

            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['nullable', 'string', 'max:255', 'required_if:create_new,true'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],

            'custom_title' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string'],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],

            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_note' => ['nullable', 'string'],
            'self_pay_amount' => ['nullable', 'numeric', 'min:0'],
            'self_pay_note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $branch) {
            if ($data['create_new']) {
                $baseSlug = $data['slug'] ?: Str::slug($data['name']);
                $slug = $baseSlug;
                $counter = 1;

                while (Service::where('company_id', $branch->company_id)->where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }

                $service = Service::create([
                    'company_id' => $branch->company_id,
                    'category_id' => $data['category_id'] ?? null,
                    'name' => $data['name'],
                    'slug' => $slug,
                    'short_description' => $data['short_description'] ?? null,
                    'description' => $data['description'] ?? null,
                    'icon' => $data['icon'] ?? null,
                    'duration_minutes' => $data['duration_minutes'] ?? null,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            } else {
                $service = Service::query()
                    ->where('company_id', $branch->company_id)
                    ->findOrFail($data['service_id']);

                abort_if($service->company_id !== $branch->company_id, 403);
            }

            $branchService = BranchService::create([
                'branch_id' => $branch->id,
                'service_id' => $service->id,
                'custom_title' => $data['custom_title'] ?? null,
                'custom_description' => $data['custom_description'] ?? null,
                'is_available' => $data['is_available'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $branchService->prices()->create([
                'price_type' => 'insurance',
                'amount' => $data['insurance_amount'] ?? null,
                'currency' => 'EUR',
                'note' => $data['insurance_note'] ?? null,
                'is_visible' => true,
                'sort_order' => 1,
            ]);

            $branchService->prices()->create([
                'price_type' => 'self_pay',
                'amount' => $data['self_pay_amount'] ?? null,
                'currency' => 'EUR',
                'note' => $data['self_pay_note'] ?? null,
                'is_visible' => true,
                'sort_order' => 2,
            ]);
        });

        return back()->with('success', 'Služba bola pridaná k pobočke.');
    }

    public function update(Request $request, Branch $branch, BranchService $branchService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchService->branch_id !== $branch->id, 404);

        $data = $request->validate([
            'custom_title' => ['nullable', 'string', 'max:255'],
            'custom_description' => ['nullable', 'string'],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],

            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_note' => ['nullable', 'string'],
            'self_pay_amount' => ['nullable', 'numeric', 'min:0'],
            'self_pay_note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $branchService) {
            $branchService->update([
                'custom_title' => $data['custom_title'] ?? null,
                'custom_description' => $data['custom_description'] ?? null,
                'is_available' => $data['is_available'],
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $branchService->prices()->updateOrCreate(
                ['price_type' => 'insurance'],
                [
                    'amount' => $data['insurance_amount'] ?? null,
                    'currency' => 'EUR',
                    'note' => $data['insurance_note'] ?? null,
                    'is_visible' => true,
                    'sort_order' => 1,
                ]
            );

            $branchService->prices()->updateOrCreate(
                ['price_type' => 'self_pay'],
                [
                    'amount' => $data['self_pay_amount'] ?? null,
                    'currency' => 'EUR',
                    'note' => $data['self_pay_note'] ?? null,
                    'is_visible' => true,
                    'sort_order' => 2,
                ]
            );
        });

        return back()->with('success', 'Služba pobočky bola upravená.');
    }

    public function destroy(Request $request, Branch $branch, BranchService $branchService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchService->branch_id !== $branch->id, 404);

        $branchService->delete();

        return back()->with('success', 'Služba bola odstránená z pobočky.');
    }
}