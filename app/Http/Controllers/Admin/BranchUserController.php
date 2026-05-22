<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class BranchUserController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        $data = $request->validate([
            'create_new' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'exists:users,id'],

            // when creating new user
            'first_name' => ['required_if:create_new,true', 'string', 'max:255'],
            'last_name' => ['required_if:create_new,true', 'string', 'max:255'],
            'email' => ['required_if:create_new,true', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required_if:create_new,true', 'string', 'min:8'],
            'global_role' => ['required_if:create_new,true', Rule::in(['super_admin', 'admin', 'editor', 'viewer'])],

            'role' => [
                'required',
                'string',
                Rule::in(['branch_admin', 'branch_editor', 'viewer']),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! empty($data['create_new'])) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'global_role' => $data['global_role'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        } else {
            $user = User::findOrFail($data['user_id']);

            if (! in_array($user->global_role, ['admin', 'editor', 'viewer'], true)) {
                return back()->withErrors([
                    'user_id' => 'Používateľ nemôže byť priradený k pobočke.',
                ]);
            }
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