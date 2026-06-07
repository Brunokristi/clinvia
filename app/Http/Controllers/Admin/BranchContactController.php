<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchContactController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'type' => [
                'required',
                'string',
                Rule::in([
                    'phone',
                    'email',
                    'website',
                    'facebook',
                    'instagram',
                    'booking_phone',
                    'billing_email',
                    'other',
                ]),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'is_primary' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($branch, $data): void {
            $data['sort_order'] = $data['sort_order'] ?? 0;

            $shouldBePrimary = (bool) $data['is_primary']
                || ! $branch->contacts()->exists();

            $data['is_primary'] = $shouldBePrimary;

            if ($shouldBePrimary) {
                $branch->contacts()->update([
                    'is_primary' => false,
                ]);
            }

            $branch->contacts()->create($data);

            $this->ensurePrimaryContact($branch);
        });

        return back()->with('success', 'Kontakt bol pridaný.');
    }

    public function update(Request $request, Branch $branch, Contact $contact): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($contact->branch_id !== $branch->id, 404);

        $data = $request->validate([
            'type' => [
                'required',
                'string',
                Rule::in([
                    'phone',
                    'email',
                    'website',
                    'facebook',
                    'instagram',
                    'booking_phone',
                    'billing_email',
                    'other',
                ]),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'is_primary' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($branch, $contact, $data): void {
            $data['sort_order'] = $data['sort_order'] ?? 0;

            if ((bool) $data['is_primary']) {
                $branch->contacts()
                    ->whereKeyNot($contact->id)
                    ->update([
                        'is_primary' => false,
                    ]);
            }

            $contact->update($data);

            $this->ensurePrimaryContact($branch);
        });

        return back()->with('success', 'Kontakt bol upravený.');
    }

    public function destroy(Request $request, Branch $branch, Contact $contact): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($contact->branch_id !== $branch->id, 404);

        DB::transaction(function () use ($branch, $contact): void {
            $contact->delete();

            $this->ensurePrimaryContact($branch);
        });

        return back()->with('success', 'Kontakt bol odstránený.');
    }

    private function ensurePrimaryContact(Branch $branch): void
    {
        $contacts = $branch->contacts()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($contacts->isEmpty()) {
            return;
        }

        $primaryContact = $contacts->firstWhere('is_primary', true)
            ?? $contacts->first();

        $branch->contacts()
            ->whereKeyNot($primaryContact->id)
            ->update([
                'is_primary' => false,
            ]);

        if (! $primaryContact->is_primary) {
            $primaryContact->forceFill([
                'is_primary' => true,
            ])->save();
        }
    }
}