<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function edit(Request $request, Service $service): Response
    {
        abort_if(! $this->canAccessService($request, $service), 403);

        return Inertia::render('Admin/Services/Edit', [
            'service' => $service->load([
                'category',
                'information',
                'necessities',
                'steps',
                'tags',
                'files',
            ]),
            'categories' => Category::query()
                ->select(['id', 'name'])
                ->where('company_id', $service->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        abort_if(! $this->canAccessService($request, $service), 403);

        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'slug')
                    ->where('company_id', $service->company_id)
                    ->ignore($service->id),
            ],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $service->update([
            ...$data,
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Služba bola upravená.');
    }

    private function canAccessService(Request $request, Service $service): bool
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->canAccessCompany($service->company_id)) {
            return true;
        }

        return $service->branches()
            ->whereIn('branches.id', $user->branches()
                ->wherePivot('is_active', true)
                ->select('branches.id')
            )
            ->exists();
    }
}
