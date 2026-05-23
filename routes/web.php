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
use App\Http\Controllers\Admin\BranchServiceController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServiceInformationController;
use App\Http\Controllers\Admin\ServiceNecessityController;
use App\Http\Controllers\Admin\ServiceStepController;
use App\Http\Controllers\Admin\ServiceTagController;
use App\Http\Controllers\Admin\ServiceFileController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Api\PublicCompanyController;
use App\Http\Controllers\Admin\ApiClientController;



Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('api.client')->prefix('public')->group(function () {
    Route::get('/companies/{company:slug}', [PublicCompanyController::class, 'show'])
        ->name('api.public.companies.show');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('superadmin')->group(function () {
        Route::resource('/users', UserController::class)
            ->except(['show']);

        Route::resource('/api-clients', ApiClientController::class)
            ->except(['show']);

        Route::post('/api-clients/{apiClient}/regenerate', [ApiClientController::class, 'regenerate'])
            ->name('api-clients.regenerate');

        Route::get('/companies/create', [CompanyController::class, 'create'])
            ->name('companies.create');

        Route::post('/companies', [CompanyController::class, 'store'])
            ->name('companies.store');

        Route::get('/companies/onboard', [CompanyController::class, 'onboard'])
            ->name('companies.onboard');

        Route::post('/companies/onboard', [CompanyController::class, 'storeOnboard'])
            ->name('companies.onboard.store');
    });

    Route::middleware('manage.companies')->group(function () {
        Route::get('/users/lookup/by-email', [UserController::class, 'lookupByEmail'])
            ->name('users.lookup-by-email');

        Route::get('/users/lookup/email-suggestions', [UserController::class, 'emailSuggestions'])
            ->name('users.lookup-email-suggestions');

        Route::get('/companies', [CompanyController::class, 'index'])
            ->name('companies.index');

        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])
            ->name('companies.edit');

        Route::get('/companies/{company}/branches', [CompanyController::class, 'branches'])
            ->name('companies.branches');

        Route::get('/companies/{company}/users', [CompanyController::class, 'users'])
            ->name('companies.users.page');

        Route::post('/companies/{company}/users', [CompanyUserController::class, 'store'])
            ->name('companies.users.store');

        Route::delete('/companies/{company}/users/{user}', [CompanyUserController::class, 'destroy'])
            ->name('companies.users.destroy');

        Route::post('/companies/{company}/invitations/{companyInvitation}/resend', [CompanyUserController::class, 'resendInvitation'])
            ->name('companies.invitations.resend');

        Route::delete('/companies/{company}/invitations/{companyInvitation}', [CompanyUserController::class, 'destroyInvitation'])
            ->name('companies.invitations.destroy');

        Route::put('/companies/{company}', [CompanyController::class, 'update'])
            ->name('companies.update');

        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
            ->name('companies.destroy');
    });

    Route::get('/companies/{company}/api-clients', [CompanyController::class, 'apiClients'])
        ->middleware('superadmin')
        ->name('companies.api-clients');

    Route::middleware('manage.branches')->group(function () {
        Route::resource('/branches', BranchController::class)
            ->except(['show']);

        Route::get('/branches/{branch}/contacts', [BranchController::class, 'contacts'])
            ->name('branches.contacts.page');

        Route::get('/branches/{branch}/opening-hours', [BranchController::class, 'openingHours'])
            ->name('branches.opening-hours.page');

        Route::get('/branches/{branch}/users', [BranchController::class, 'users'])
            ->name('branches.users.page');

        Route::post('/branches/{branch}/users', [BranchUserController::class, 'store'])
            ->name('branches.users.store');

        Route::delete('/branches/{branch}/users/{user}', [BranchUserController::class, 'destroy'])
            ->name('branches.users.destroy');

        Route::post('/branches/{branch}/invitations/{branchInvitation}/resend', [BranchUserController::class, 'resendInvitation'])
            ->name('branches.invitations.resend');

        Route::delete('/branches/{branch}/invitations/{branchInvitation}', [BranchUserController::class, 'destroyInvitation'])
            ->name('branches.invitations.destroy');

        Route::get('/branches/{branch}/employees', [BranchController::class, 'employees'])
            ->name('branches.employees.page');

        Route::get('/branches/{branch}/services', [BranchController::class, 'services'])
            ->name('branches.services.page');

        Route::post('/branches/{branch}/contacts', [BranchContactController::class, 'store'])
            ->name('branches.contacts.store');

        Route::put('/branches/{branch}/contacts/{contact}', [BranchContactController::class, 'update'])
            ->name('branches.contacts.update');

        Route::delete('/branches/{branch}/contacts/{contact}', [BranchContactController::class, 'destroy'])
            ->name('branches.contacts.destroy');

        Route::put('/branches/{branch}/opening-hours', [BranchOpeningHoursController::class, 'update'])
            ->name('branches.opening-hours.update');
        
        Route::post('/branches/{branch}/employees', [BranchEmployeeController::class, 'store'])
            ->name('branches.employees.store');

        Route::put('/branches/{branch}/employees/{employee}', [BranchEmployeeController::class, 'update'])
            ->name('branches.employees.update');

        Route::delete('/branches/{branch}/employees/{employee}', [BranchEmployeeController::class, 'destroy'])
            ->name('branches.employees.destroy');

        Route::post('/branches/{branch}/services', [BranchServiceController::class, 'store'])
            ->name('branches.services.store');

        Route::put('/branches/{branch}/services/{branchService}', [BranchServiceController::class, 'update'])
            ->name('branches.services.update');

        Route::delete('/branches/{branch}/services/{branchService}', [BranchServiceController::class, 'destroy'])
            ->name('branches.services.destroy');

        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])
            ->name('services.edit');

        Route::put('/services/{service}', [ServiceController::class, 'update'])
            ->name('services.update');

        Route::post('/services/{service}/information', [ServiceInformationController::class, 'store'])
            ->name('services.information.store');

        Route::delete('/services/{service}/information/{information}', [ServiceInformationController::class, 'destroy'])
            ->name('services.information.destroy');

        Route::post('/services/{service}/necessities', [ServiceNecessityController::class, 'store'])
            ->name('services.necessities.store');

        Route::delete('/services/{service}/necessities/{necessity}', [ServiceNecessityController::class, 'destroy'])
            ->name('services.necessities.destroy');

        Route::post('/services/{service}/steps', [ServiceStepController::class, 'store'])
            ->name('services.steps.store');

        Route::delete('/services/{service}/steps/{step}', [ServiceStepController::class, 'destroy'])
            ->name('services.steps.destroy');

        Route::post('/services/{service}/tags', [ServiceTagController::class, 'store'])
            ->name('services.tags.store');

        Route::delete('/services/{service}/tags/{tag}', [ServiceTagController::class, 'destroy'])
            ->name('services.tags.destroy');

        Route::post('/services/{service}/files', [ServiceFileController::class, 'store'])
            ->name('services.files.store');

        Route::delete('/services/{service}/files/{file}', [ServiceFileController::class, 'destroy'])
            ->name('services.files.destroy');
    });
});


require __DIR__.'/auth.php';
