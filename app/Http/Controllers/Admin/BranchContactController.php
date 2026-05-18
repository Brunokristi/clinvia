<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchContactController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in([
                'phone',
                'email',
                'website',
                'facebook',
                'instagram',
                'booking_phone',
                'billing_email',
                'other',
            ])],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'is_primary' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($data['is_primary']) {
            $branch->contacts()
                ->where('type', $data['type'])
                ->update(['is_primary' => false]);
        }

        $branch->contacts()->create($data);

        return back()->with('success', 'Kontakt bol pridaný.');
    }

    public function update(Request $request, Branch $branch, Contact $contact): RedirectResponse
    {
        abort_if($contact->branch_id !== $branch->id, 404);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in([
                'phone',
                'email',
                'website',
                'facebook',
                'instagram',
                'booking_phone',
                'billing_email',
                'other',
            ])],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'is_primary' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($data['is_primary']) {
            $branch->contacts()
                ->where('type', $data['type'])
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($data);

        return back()->with('success', 'Kontakt bol upravený.');
    }

    public function destroy(Branch $branch, Contact $contact): RedirectResponse
    {
        abort_if($contact->branch_id !== $branch->id, 404);

        $contact->delete();

        return back()->with('success', 'Kontakt bol odstránený.');
    }
}