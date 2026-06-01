<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchPublicSiteController extends Controller
{
    public function edit(Branch $branch): Response
    {
        $branch->load([
            'company',
            'publicSite',
        ]);

        return Inertia::render('Admin/Branches/PublicSite', [
            'branch' => $branch,
            'templates' => [
                [
                    'label' => 'Predvolený',
                    'value' => 'default',
                ],
            ],
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'is_enabled' => ['boolean'],
            'template' => ['required', 'string', 'in:default'],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('branch_public_sites', 'custom_domain')
                    ->ignore($branch->publicSite?->id),
            ],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
        ]);

        $branch->publicSite()->updateOrCreate(
            [
                'branch_id' => $branch->id,
            ],
            $validated,
        );

        return back()->with('success', 'Nastavenia verejnej stránky boli uložené.');
    }

    public function updateFaqItems(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'faq_items' => ['nullable', 'array'],
            'faq_items.*.question' => ['required', 'string', 'max:255'],
            'faq_items.*.answer' => ['required', 'string', 'max:1000'],
        ]);

        $branch->publicSite()->updateOrCreate(
            [
                'branch_id' => $branch->id,
            ],
            [
                'faq_items' => array_values($validated['faq_items'] ?? []),
            ],
        );

        return back()->with('success', 'Otázky a odpovede boli uložené.');
    }
}