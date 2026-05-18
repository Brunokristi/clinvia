<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\BranchController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('superadmin')->group(function () {
        Route::resource('/users', UserController::class)
            ->except(['show']);
        Route::resource('/companies', CompanyController::class)
            ->except(['show']);
        });
        Route::resource('/branches', BranchController::class)
        ->except(['show']);
});

require __DIR__.'/auth.php';
