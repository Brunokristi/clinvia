<?php

namespace App\Modules\Calendar\Services;

use App\Models\Branch;
use App\Models\User;

class CalendarEntitlementService
{
    public function userCanManageCalendar(User $user, Branch $branch): bool
    {
        if (! $user->canAccessBranch($branch)) {
            return false;
        }

        return $this->isBranchAddonEnabled($branch) && $this->isCompanyAddonEnabled($branch);
    }

    public function branchAllowsPublicBooking(Branch $branch): bool
    {
        $settings = $branch->booking_settings ?? [];

        return (bool) ($settings['is_enabled'] ?? false)
            && (bool) ($settings['booking_addon_enabled'] ?? true);
    }

    public function isBranchAddonEnabled(Branch $branch): bool
    {
        $settings = $branch->booking_settings ?? [];

        return (bool) ($settings['calendar_addon_enabled'] ?? true);
    }

    public function isCompanyAddonEnabled(Branch $branch): bool
    {
        $company = $branch->company;

        if (! $company) {
            return true;
        }

        $subscription = data_get($company, 'subscription_settings', []);

        return (bool) ($subscription['calendar_addon_enabled'] ?? true);
    }
}
