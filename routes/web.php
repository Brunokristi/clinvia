<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchContactController;
use App\Http\Controllers\Admin\BranchOpeningHoursController;
use App\Http\Controllers\Admin\BranchUserController;
use App\Http\Controllers\Admin\BranchEmployeeController;

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

    Route::middleware('manage.branches')->group(function () {
        Route::resource('/branches', BranchController::class)
            ->except(['show']);

        Route::post('/branches/{branch}/contacts', [BranchContactController::class, 'store'])
            ->name('branches.contacts.store');

        Route::put('/branches/{branch}/contacts/{contact}', [BranchContactController::class, 'update'])
            ->name('branches.contacts.update');

        Route::delete('/branches/{branch}/contacts/{contact}', [BranchContactController::class, 'destroy'])
            ->name('branches.contacts.destroy');

        Route::put('/branches/{branch}/opening-hours', [BranchOpeningHoursController::class, 'update'])
            ->name('branches.opening-hours.update');
        
        Route::post('/branches/{branch}/users', [BranchUserController::class, 'store'])
            ->name('branches.users.store');

        Route::delete('/branches/{branch}/users/{user}', [BranchUserController::class, 'destroy'])
            ->name('branches.users.destroy');
        
        Route::post('/branches/{branch}/employees', [BranchEmployeeController::class, 'store'])
            ->name('branches.employees.store');

        Route::delete('/branches/{branch}/employees/{employee}', [BranchEmployeeController::class, 'destroy'])
            ->name('branches.employees.destroy');
    });
});


require __DIR__.'/auth.php';
