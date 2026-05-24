<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Category;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchServiceController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'duration_sessions' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],

            'new_category_name' => ['nullable', 'string', 'max:255'],

            'is_available' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],

            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_note' => ['nullable', 'string'],
            'self_pay_amount' => ['nullable', 'numeric', 'min:0'],
            'self_pay_note' => ['nullable', 'string'],
        ]);

        // ensure we have a category name when creating a new category without selecting existing
        if (empty($data['category_id']) && empty($data['new_category_name'])) {
            throw ValidationException::withMessages(['new_category_name' => ['Pri vytváraní novej kategórie je potrebný názov.']]);
        }

        DB::transaction(function () use ($data, $branch) {
            // If the user requested a new category name, create it first
            $categoryId = $data['category_id'] ?? null;

            if (empty($categoryId) && ! empty($data['new_category_name'])) {
                $baseCatSlug = Str::slug($data['new_category_name']);
                $catSlug = $baseCatSlug;
                $catCounter = 1;

                while (Category::where('company_id', $branch->company_id)->where('slug', $catSlug)->exists()) {
                    $catSlug = $baseCatSlug . '-' . $catCounter;
                    $catCounter++;
                }

                $category = Category::create([
                    'company_id' => $branch->company_id,
                    'name' => $data['new_category_name'],
                    'slug' => $catSlug,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);

                $categoryId = $category->id;
            }

            $baseSlug = $data['slug'] ?: Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;

            while (Service::where('branch_id', $branch->id)->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            Service::create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'category_id' => $categoryId,
                'name' => $data['name'],
                'slug' => $slug,
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'duration_sessions' => $data['duration_sessions'] ?? 1,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'insurance_amount' => $data['insurance_amount'] ?? null,
                'insurance_note' => $data['insurance_note'] ?? null,
                'self_pay_amount' => $data['self_pay_amount'] ?? null,
                'self_pay_note' => $data['self_pay_note'] ?? null,
                'is_active' => $data['is_available'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        });

        return back()->with('success', 'Služba bola vytvorená pre pobočku.');
    }

    public function update(Request $request, Branch $branch, Service $branchService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchService->branch_id !== $branch->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'duration_sessions' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],

            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'insurance_note' => ['nullable', 'string'],
            'self_pay_amount' => ['nullable', 'numeric', 'min:0'],
            'self_pay_note' => ['nullable', 'string'],
        ]);

        $branchService->update([
            'name' => $data['name'],
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'duration_sessions' => $data['duration_sessions'] ?? 1,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'is_active' => $data['is_available'],
            'sort_order' => $data['sort_order'] ?? 0,
            'insurance_amount' => $data['insurance_amount'] ?? null,
            'insurance_note' => $data['insurance_note'] ?? null,
            'self_pay_amount' => $data['self_pay_amount'] ?? null,
            'self_pay_note' => $data['self_pay_note'] ?? null,
        ]);

        return back()->with('success', 'Služba pobočky bola upravená.');
    }

    public function destroy(Request $request, Branch $branch, Service $branchService): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchService->branch_id !== $branch->id, 404);

        $branchService->delete();

        return back()->with('success', 'Služba bola odstránená z pobočky.');
    }
}