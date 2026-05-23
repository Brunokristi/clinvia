<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchInvitation;
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

        $branchRole = $branch->users()
            ->whereKey($user->id)
            ->first()?->pivot?->role;

        if ($branchRole === 'branch_admin' && ! $request->user()->isAdmin()) {
            abort(403);
        }

        $branch->users()->detach($user->id);

        return back()->with('success', 'Používateľ bol odstránený z pobočky.');
    }

    public function resendInvitation(Request $request, Branch $branch, BranchInvitation $branchInvitation): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchInvitation->branch_id !== $branch->id, 404);
        abort_if($branchInvitation->isAccepted(), 422);

        app(UserInvitationService::class)->resendBranchInvitation($branchInvitation, $request->user());

        return back()->with('success', 'Pozvánka bola znovu odoslaná.');
    }

    public function destroyInvitation(Request $request, Branch $branch, BranchInvitation $branchInvitation): RedirectResponse
    {
        abort_if(! $request->user()->canAccessBranch($branch), 403);
        abort_if($branchInvitation->branch_id !== $branch->id, 404);

        $branchInvitation->delete();

        return back()->with('success', 'Pozvánka bola odstránená.');
    }
}