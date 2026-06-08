<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserCanManageBranches;
use App\Http\Middleware\EnsureUserCanManageCompanies;
use App\Http\Middleware\EnsureValidApiClient;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'superadmin' => EnsureUserIsSuperAdmin::class,
            'manage.branches' => EnsureUserCanManageBranches::class,
            'manage.companies' => EnsureUserCanManageCompanies::class,
            'active' => EnsureUserIsActive::class,
            'api.client' => EnsureValidApiClient::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
