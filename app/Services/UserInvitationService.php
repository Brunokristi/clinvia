<?php

namespace App\Services;

use App\Mail\BranchInvitationMail;
use App\Mail\CompanyInvitationMail;
use App\Models\Branch;
use App\Models\BranchInvitation;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserInvitationService
{
    public function sendCompanyInvitation(Company $company, string $email, ?User $invitedBy = null): CompanyInvitation
    {
        [$invitation, $plainToken] = $this->createCompanyInvitation($company, $email, $invitedBy);

        Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation, $plainToken));

        return $invitation;
    }

    public function sendBranchInvitation(Branch $branch, string $email, ?User $invitedBy = null): BranchInvitation
    {
        [$invitation, $plainToken] = $this->createBranchInvitation($branch, $email, $invitedBy);

        Mail::to($invitation->email)->send(new BranchInvitationMail($invitation, $plainToken));

        return $invitation;
    }

    public function acceptExistingCompanyInvitation(CompanyInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            UserCompany::firstOrCreate([
                'user_id' => $user->id,
                'company_id' => $invitation->company_id,
            ], [
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();
        });
    }

    public function acceptExistingBranchInvitation(BranchInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            UserBranch::firstOrCreate([
                'user_id' => $user->id,
                'branch_id' => $invitation->branch_id,
            ], [
                'role' => 'branch_admin',
                'is_active' => true,
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();
        });
    }

    public function createNewCompanyUser(CompanyInvitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'global_role' => 'admin',
                'is_active' => true,
            ]);

            UserCompany::firstOrCreate([
                'user_id' => $user->id,
                'company_id' => $invitation->company_id,
            ], [
                'role' => 'company_admin',
                'is_active' => true,
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();

            return $user;
        });
    }

    public function createNewBranchUser(BranchInvitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'global_role' => 'admin',
                'is_active' => true,
            ]);

            UserBranch::firstOrCreate([
                'user_id' => $user->id,
                'branch_id' => $invitation->branch_id,
            ], [
                'role' => 'branch_admin',
                'is_active' => true,
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();

            return $user;
        });
    }

    private function createCompanyInvitation(Company $company, string $email, ?User $invitedBy): array
    {
        $plainToken = Str::random(64);

        $invitation = CompanyInvitation::create([
            'company_id' => $company->id,
            'invited_by_user_id' => $invitedBy?->id,
            'email' => $email,
            'token_hash' => Hash::make($plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        return [$invitation, $plainToken];
    }

    private function createBranchInvitation(Branch $branch, string $email, ?User $invitedBy): array
    {
        $plainToken = Str::random(64);

        $invitation = BranchInvitation::create([
            'branch_id' => $branch->id,
            'invited_by_user_id' => $invitedBy?->id,
            'email' => $email,
            'token_hash' => Hash::make($plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        return [$invitation, $plainToken];
    }
}