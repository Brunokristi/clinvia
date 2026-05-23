<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyUserController extends Controller
{
    public function store(Request $request, Company $company): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($company->id), 403);

        $data = $request->validate([
            'invite_email' => ['required', 'email', 'max:255'],
        ]);

        app(UserInvitationService::class)->sendCompanyInvitation(
            $company,
            $data['invite_email'],
            $request->user(),
        );

        return back()->with('success', 'Pozvánka do firmy bola odoslaná.');
    }

    public function destroy(Request $request, Company $company, User $user): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($company->id), 403);

        $companyRole = $company->users()
            ->whereKey($user->id)
            ->first()?->pivot?->role;

        if ($companyRole === 'company_admin' && ! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $company->users()->detach($user->id);

        return back()->with('success', 'Používateľ bol odstránený z firmy.');
    }

    public function resendInvitation(Request $request, Company $company, CompanyInvitation $companyInvitation): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($company->id), 403);
        abort_if($companyInvitation->company_id !== $company->id, 404);
        abort_if($companyInvitation->isAccepted(), 422);

        app(UserInvitationService::class)->resendCompanyInvitation($companyInvitation, $request->user());

        return back()->with('success', 'Pozvánka bola znovu odoslaná.');
    }

    public function destroyInvitation(Request $request, Company $company, CompanyInvitation $companyInvitation): RedirectResponse
    {
        abort_if(! $request->user()->canAccessCompany($company->id), 403);
        abort_if($companyInvitation->company_id !== $company->id, 404);

        $companyInvitation->delete();

        return back()->with('success', 'Pozvánka bola odstránená.');
    }
}