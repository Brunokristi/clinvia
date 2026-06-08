<?php

use App\Models\Branch;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('branches.{branchId}.inbox', function ($user, int $branchId) {
    $branch = Branch::query()->find($branchId);

    if (! $branch) {
        return false;
    }

    return $user->canAccessBranch($branch);
});

Broadcast::channel('branches.{branchId}.calendar', function ($user, int $branchId) {
    $branch = Branch::query()->find($branchId);

    if (! $branch) {
        return false;
    }

    return $user->canAccessBranch($branch);
});