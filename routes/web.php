<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchContactController;

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

        Route::post('/branches/{branch}/contacts', [BranchContactController::class, 'store'])
            ->name('branches.contacts.store');

        Route::put('/branches/{branch}/contacts/{contact}', [BranchContactController::class, 'update'])
            ->name('branches.contacts.update');

        Route::delete('/branches/{branch}/contacts/{contact}', [BranchContactController::class, 'destroy'])
            ->name('branches.contacts.destroy');
        });

require __DIR__.'/auth.php';
