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
use Illuminate\Validation\ValidationException;

class UserInvitationService
{
    public function sendCompanyInvitation(Company $company, string $email, ?User $invitedBy = null): CompanyInvitation
    {
        $this->ensureCompanyInvitationCanBeSent($company, $email);

        [$invitation, $plainToken] = $this->issueCompanyInvitation($company, $email, $invitedBy);

        Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation, $plainToken));

        return $invitation;
    }

    public function resendCompanyInvitation(CompanyInvitation $invitation, ?User $invitedBy = null): CompanyInvitation
    {
        [$invitation, $plainToken] = $this->issueCompanyInvitation($invitation->company, $invitation->email, $invitedBy, $invitation);

        Mail::to($invitation->email)->send(new CompanyInvitationMail($invitation, $plainToken));

        return $invitation;
    }

    public function sendBranchInvitation(Branch $branch, string $email, ?User $invitedBy = null): BranchInvitation
    {
        $this->ensureBranchInvitationCanBeSent($branch, $email, $invitedBy);

        [$invitation, $plainToken] = $this->issueBranchInvitation($branch, $email, $invitedBy);

        Mail::to($invitation->email)->send(new BranchInvitationMail($invitation, $plainToken));

        return $invitation;
    }

    public function resendBranchInvitation(BranchInvitation $invitation, ?User $invitedBy = null): BranchInvitation
    {
        [$invitation, $plainToken] = $this->issueBranchInvitation($invitation->branch, $invitation->email, $invitedBy, $invitation);

        Mail::to($invitation->email)->send(new BranchInvitationMail($invitation, $plainToken));

        return $invitation;
    }

    public function acceptExistingCompanyInvitation(CompanyInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            if (! $user->isSuperAdmin()) {
                $user->forceFill([
                    'global_role' => 'admin',
                ])->save();
            }

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

            $user->syncGlobalRoleWithMemberships();

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
                'global_role' => 'editor',
                'is_active' => true,
            ]);

            UserBranch::firstOrCreate([
                'user_id' => $user->id,
                'branch_id' => $invitation->branch_id,
            ], [
                'role' => 'branch_admin',
                'is_active' => true,
            ]);

            $user->syncGlobalRoleWithMemberships();

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();

            return $user;
        });
    }

    private function issueCompanyInvitation(Company $company, string $email, ?User $invitedBy, ?CompanyInvitation $invitation = null): array
    {
        $plainToken = Str::random(64);

        $payload = [
            'company_id' => $company->id,
            'invited_by_user_id' => $invitedBy?->id,
            'email' => $email,
            'token_hash' => Hash::make($plainToken),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];

        if ($invitation) {
            $invitation->forceFill($payload)->save();

            return [$invitation, $plainToken];
        }

        $invitation = CompanyInvitation::create($payload);

        return [$invitation, $plainToken];
    }

    private function issueBranchInvitation(Branch $branch, string $email, ?User $invitedBy, ?BranchInvitation $invitation = null): array
    {
        $plainToken = Str::random(64);

        $payload = [
            'branch_id' => $branch->id,
            'invited_by_user_id' => $invitedBy?->id,
            'email' => $email,
            'token_hash' => Hash::make($plainToken),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];

        if ($invitation) {
            $invitation->forceFill($payload)->save();

            return [$invitation, $plainToken];
        }

        $invitation = BranchInvitation::create($payload);

        return [$invitation, $plainToken];
    }

    private function ensureCompanyInvitationCanBeSent(Company $company, string $email): void
    {
        $normalizedEmail = Str::lower($email);

        if ($company->users()
            ->whereRaw('LOWER(users.email) = ?', [$normalizedEmail])
            ->wherePivot('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'invite_email' => 'Tento používateľ už má aktívny prístup k firme.',
            ]);
        }

        if ($company->companyInvitations()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNull('accepted_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'invite_email' => 'Pre tento email už existuje pozvánka. Použi znovu odoslať.',
            ]);
        }
    }

    private function ensureBranchInvitationCanBeSent(Branch $branch, string $email, ?User $invitedBy): void
    {
        $normalizedEmail = Str::lower($email);

        if ($invitedBy !== null && Str::lower($invitedBy->email) === $normalizedEmail) {
            throw ValidationException::withMessages([
                'invite_email' => 'Nemôžeš pozvať samého seba ako branch admina.',
            ]);
        }

        if ($branch->users()
            ->whereRaw('LOWER(users.email) = ?', [$normalizedEmail])
            ->wherePivot('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'invite_email' => 'Tento používateľ už má aktívny prístup k pobočke.',
            ]);
        }

        if ($branch->branchInvitations()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNull('accepted_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'invite_email' => 'Pre tento email už existuje pozvánka. Použi znovu odoslať.',
            ]);
        }
    }
}