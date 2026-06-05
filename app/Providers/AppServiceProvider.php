<?php

namespace App\Providers;

use App\Models\Branch;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Broadcast::channel('branch.{branchId}', function ($user, $branchId) {
            if (! $user) {
                return false;
            }

            $branch = Branch::find($branchId);

            return $branch && $user->canAccessBranch($branch);
        });
    }
}
