<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchPatientController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $this->validatePayload($request);

        $branch->patients()->create([
            ...$data,
            'last_used_at' => now(),
        ]);

        return back()->with('success', 'Pacient bol pridaný.');
    }

    public function update(Request $request, Branch $branch, Patient $patient): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $patient->branch_id !== (int) $branch->id, 404);

        $data = $this->validatePayload($request);

        $patient->update([
            ...$data,
            'last_used_at' => now(),
        ]);

        return back()->with('success', 'Pacient bol upravený.');
    }

    public function destroy(Request $request, Branch $branch, Patient $patient): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if((int) $patient->branch_id !== (int) $branch->id, 404);

        $patient->delete();

        return back()->with('success', 'Pacient bol odstránený.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:255'],
            'patient_birth_number' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
