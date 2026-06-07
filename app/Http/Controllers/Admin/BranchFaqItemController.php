<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchFaqItemController extends Controller
{
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'faq_items' => ['nullable', 'array'],
            'faq_items.*.question' => ['required', 'string', 'max:255'],
            'faq_items.*.answer' => ['required', 'string'],
        ]);

        $faqItems = collect($data['faq_items'] ?? [])
            ->map(fn (array $item): array => [
                'question' => trim($item['question'] ?? ''),
                'answer' => trim($item['answer'] ?? ''),
            ])
            ->filter(fn (array $item): bool => filled($item['question']) && filled($item['answer']))
            ->values()
            ->all();

        $branch->publicSite()->updateOrCreate(
            [
                'branch_id' => $branch->id,
            ],
            [
                'faq_items' => $faqItems,
            ],
        );

        return back()->with('success', 'Otázky a odpovede boli uložené.');
    }
}