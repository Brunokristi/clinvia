<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchReplyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchReplyTemplateController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $branch->replyTemplates()->create($validated);

        return back()->with('success', 'Šablóna odpovede bola vytvorená.');
    }

    public function update(
        Request $request,
        Branch $branch,
        BranchReplyTemplate $replyTemplate,
    ): RedirectResponse {
        abort_unless($replyTemplate->branch_id === $branch->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $replyTemplate->update($validated);

        return back()->with('success', 'Šablóna odpovede bola upravená.');
    }

    public function destroy(Branch $branch, BranchReplyTemplate $replyTemplate): RedirectResponse
    {
        abort_unless($replyTemplate->branch_id === $branch->id, 404);

        $replyTemplate->delete();

        return back()->with('success', 'Šablóna odpovede bola odstránená.');
    }
}