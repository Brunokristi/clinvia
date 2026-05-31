<?php

use App\Http\Controllers\Admin\ApiClientController;
use App\Http\Controllers\Admin\BranchContactController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchEmployeeController;
use App\Http\Controllers\Admin\BranchOpeningHoursController;
use App\Http\Controllers\Admin\BranchServiceController;
use App\Http\Controllers\Admin\BranchUserController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServiceFileController;
use App\Http\Controllers\Admin\ServiceInformationController;
use App\Http\Controllers\Admin\ServiceNecessityController;
use App\Http\Controllers\Admin\ServiceStepController;
use App\Http\Controllers\Admin\ServiceTagController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BranchPublicSiteController;
use App\Http\Controllers\Api\PublicCompanyController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('api.client')
    ->prefix('public')
    ->name('api.public.')
    ->group(function () {
        Route::get('/companies/{company:slug}', [PublicCompanyController::class, 'show'])
            ->name('companies.show');
    });

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/password', [PasswordController::class, 'update'])
        ->name('password.update');

    Route::middleware('superadmin')->group(function () {
        Route::resource('users', UserController::class)
            ->except(['show']);

        Route::resource('api-clients', ApiClientController::class)
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

        Route::get('/companies/{company}/api-clients', [CompanyController::class, 'apiClients'])
            ->name('companies.api-clients');
    });

    Route::middleware('manage.companies')->group(function () {
        Route::prefix('users/lookup')
            ->name('users.lookup.')
            ->group(function () {
                Route::get('/by-email', [UserController::class, 'lookupByEmail'])
                    ->name('by-email');

                Route::get('/email-suggestions', [UserController::class, 'emailSuggestions'])
                    ->name('email-suggestions');
            });

        Route::get('/companies', [CompanyController::class, 'index'])
            ->name('companies.index');

        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])
            ->name('companies.edit');

        Route::put('/companies/{company}', [CompanyController::class, 'update'])
            ->name('companies.update');

        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
            ->name('companies.destroy');

        Route::prefix('companies/{company}')
            ->name('companies.')
            ->group(function () {
                Route::get('/branches', [CompanyController::class, 'branches'])
                    ->name('branches');

                Route::get('/users', [CompanyController::class, 'users'])
                    ->name('users.page');

                Route::post('/users', [CompanyUserController::class, 'store'])
                    ->name('users.store');

                Route::delete('/users/{user}', [CompanyUserController::class, 'destroy'])
                    ->name('users.destroy');

                Route::post('/invitations/{companyInvitation}/resend', [CompanyUserController::class, 'resendInvitation'])
                    ->name('invitations.resend');

                Route::delete('/invitations/{companyInvitation}', [CompanyUserController::class, 'destroyInvitation'])
                    ->name('invitations.destroy');
            });
    });

    Route::middleware('manage.branches')->group(function () {
        Route::resource('branches', BranchController::class)
            ->except(['show']);

        Route::prefix('branches/{branch}')
            ->name('branches.')
            ->group(function () {
                Route::get('/contacts', [BranchController::class, 'contacts'])
                    ->name('contacts.page');

                Route::post('/contacts', [BranchContactController::class, 'store'])
                    ->name('contacts.store');

                Route::put('/contacts/{contact}', [BranchContactController::class, 'update'])
                    ->name('contacts.update');

                Route::delete('/contacts/{contact}', [BranchContactController::class, 'destroy'])
                    ->name('contacts.destroy');

                Route::get('/opening-hours', [BranchController::class, 'openingHours'])
                    ->name('opening-hours.page');

                Route::put('/opening-hours', [BranchOpeningHoursController::class, 'update'])
                    ->name('opening-hours.update');

                Route::get('/users', [BranchController::class, 'users'])
                    ->name('users.page');

                Route::post('/users', [BranchUserController::class, 'store'])
                    ->name('users.store');

                Route::delete('/users/{user}', [BranchUserController::class, 'destroy'])
                    ->name('users.destroy');

                Route::post('/invitations/{branchInvitation}/resend', [BranchUserController::class, 'resendInvitation'])
                    ->name('invitations.resend');

                Route::delete('/invitations/{branchInvitation}', [BranchUserController::class, 'destroyInvitation'])
                    ->name('invitations.destroy');

                Route::get('/employees', [BranchController::class, 'employees'])
                    ->name('employees.page');

                Route::post('/employees', [BranchEmployeeController::class, 'store'])
                    ->name('employees.store');

                Route::put('/employees/{employee}', [BranchEmployeeController::class, 'update'])
                    ->name('employees.update');

                Route::delete('/employees/{employee}', [BranchEmployeeController::class, 'destroy'])
                    ->name('employees.destroy');

                Route::get('/services', [BranchController::class, 'services'])
                    ->name('services.page');

                Route::post('/services', [BranchServiceController::class, 'store'])
                    ->name('services.store');

                Route::put('/services/{branchService}', [BranchServiceController::class, 'update'])
                    ->name('services.update');

                Route::delete('/services/{branchService}', [BranchServiceController::class, 'destroy'])
                    ->name('services.destroy');

                Route::get('/public-site', [BranchPublicSiteController::class, 'edit'])
                    ->name('public-site.edit');

                Route::put('/public-site', [BranchPublicSiteController::class, 'update'])
                    ->name('public-site.update');
            });

        Route::prefix('services/{service}')
            ->name('services.')
            ->group(function () {
                Route::get('/edit', [ServiceController::class, 'edit'])
                    ->name('edit');

                Route::put('/', [ServiceController::class, 'update'])
                    ->name('update');

                Route::post('/information', [ServiceInformationController::class, 'store'])
                    ->name('information.store');

                Route::delete('/information/{information}', [ServiceInformationController::class, 'destroy'])
                    ->name('information.destroy');

                Route::post('/necessities', [ServiceNecessityController::class, 'store'])
                    ->name('necessities.store');

                Route::delete('/necessities/{necessity}', [ServiceNecessityController::class, 'destroy'])
                    ->name('necessities.destroy');

                Route::post('/steps', [ServiceStepController::class, 'store'])
                    ->name('steps.store');

                Route::delete('/steps/{step}', [ServiceStepController::class, 'destroy'])
                    ->name('steps.destroy');

                Route::post('/tags', [ServiceTagController::class, 'store'])
                    ->name('tags.store');

                Route::delete('/tags/{tag}', [ServiceTagController::class, 'destroy'])
                    ->name('tags.destroy');

                Route::post('/files', [ServiceFileController::class, 'store'])
                    ->name('files.store');

                Route::delete('/files/{file}', [ServiceFileController::class, 'destroy'])
                    ->name('files.destroy');
            });
    });
});

require __DIR__.'/auth.php';