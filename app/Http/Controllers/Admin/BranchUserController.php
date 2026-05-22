<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\UserInvitationService;

class BranchUserController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        $data = $request->validate([
            'invite_email' => ['required', 'email', 'max:255'],
        ]);

        app(UserInvitationService::class)->sendBranchInvitation(
            $branch,
            $data['invite_email'],
            $request->user(),
        );

        return back()->with('success', 'Pozvánka do pobočky bola odoslaná.');
    }

    public function destroy(Request $request, Branch $branch, User $user): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);

        $branch->users()->detach($user->id);

        return back()->with('success', 'Používateľ bol odstránený z pobočky.');
    }
}