<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchUserController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => [
                'required',
                'string',
                Rule::in(['branch_admin', 'branch_editor', 'viewer']),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $user = User::findOrFail($data['user_id']);

        if (! in_array($user->global_role, ['admin', 'editor', 'viewer'], true)) {
            return back()->withErrors([
                'user_id' => 'Používateľ nemôže byť priradený k pobočke.',
            ]);
        }

        $branch->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $data['role'],
                'is_active' => $data['is_active'],
            ],
        ]);

        return back()->with('success', 'Používateľ bol priradený k pobočke.');
    }

    public function destroy(Request $request, Branch $branch, User $user): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $branch->users()->detach($user->id);

        return back()->with('success', 'Používateľ bol odstránený z pobočky.');
    }
}