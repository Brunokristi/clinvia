<?php

use App\Http\Controllers\Admin\ApiClientController;
use App\Http\Controllers\Admin\BranchAvailabilityRuleController;
use App\Http\Controllers\Admin\BranchBookingCalendarController;
use App\Http\Controllers\Admin\BranchBookingController;
use App\Http\Controllers\Admin\BranchDisabledDayController;
use App\Http\Controllers\Admin\BranchCapacityWindowController;
use App\Http\Controllers\Admin\BranchContactController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BranchEmployeeController;
use App\Http\Controllers\Admin\BranchFaqItemController;
use App\Http\Controllers\Admin\BranchInboxMessageController;
use App\Http\Controllers\Admin\BranchOpeningHoursController;
use App\Http\Controllers\Admin\BranchPublicSiteController;
use App\Http\Controllers\Admin\BranchReplyTemplateController;
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
use App\Http\Controllers\Api\PublicCompanyController;
use App\Http\Controllers\BranchOpeningHoursPdfController;
use App\Http\Controllers\BranchServicesPdfController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBranchSiteController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth', 'active']]);

Route::redirect('/', '/dashboard');

Route::middleware('api.client')
    ->prefix('public')
    ->name('api.public.')
    ->group(function () {
        Route::get('/companies/{company:slug}', [PublicCompanyController::class, 'show'])
            ->name('companies.show');
    });

Route::prefix('p/{branch:slug}')
    ->name('public.branch.')
    ->group(function () {
        Route::get('/', [PublicBranchSiteController::class, 'home'])
            ->name('home');

        Route::get('/sluzby', [PublicBranchSiteController::class, 'services'])
            ->name('services');

        Route::get('/sluzby/{service:slug}', [PublicBranchSiteController::class, 'service'])
            ->name('services.show');

        Route::get('/kontakt', [PublicBranchSiteController::class, 'contact'])
            ->name('contact');

        Route::get('/booking', [PublicBranchSiteController::class, 'booking'])
            ->name('booking');

        Route::post('/booking', [PublicBranchSiteController::class, 'storeBooking'])
            ->name('booking.store');

        Route::post('/contact-message', [PublicBranchSiteController::class, 'storeContactMessage'])
            ->name('contact-message.store');
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
                Route::get('/booking', [BranchBookingCalendarController::class, 'dashboard'])
                    ->name('booking.dashboard.page');

                Route::get('/booking/reservations', [BranchBookingCalendarController::class, 'index'])
                    ->name('booking.agenda.page');

                Route::get('/booking/inbox', [BranchBookingCalendarController::class, 'index'])
                    ->name('booking.inbox.page');

                Route::get('/booking/settings', [BranchController::class, 'settings'])
                    ->name('booking.settings.page');

                Route::get('/settings', [BranchController::class, 'settings'])
                    ->name('settings.page');

                Route::put('/settings', [BranchController::class, 'updateSettings'])
                    ->name('settings.update');

                Route::get('/inbox', [BranchInboxMessageController::class, 'index'])
                    ->name('inbox.index');

                Route::get('/inbox/{message}', [BranchInboxMessageController::class, 'show'])
                    ->name('inbox.show');

                Route::patch('/inbox/{message}/read', [BranchInboxMessageController::class, 'markAsRead'])
                    ->name('inbox.read');

                Route::patch('/inbox/{message}/unread', [BranchInboxMessageController::class, 'markAsUnread'])
                    ->name('inbox.unread');

                Route::delete('/inbox/{message}', [BranchInboxMessageController::class, 'destroy'])
                    ->name('inbox.destroy');

                Route::post('/inbox/{message}/reply', [BranchInboxMessageController::class, 'reply'])
                    ->name('inbox.reply');

                Route::put('/booking/services', [BranchBookingCalendarController::class, 'updateServices'])
                    ->name('booking.services.update');

                Route::get('/booking/events', [BranchBookingCalendarController::class, 'events'])
                    ->name('booking.events.index');

                /*
                |--------------------------------------------------------------------------
                | Availability rules
                |
                | These should now be used only for free availability templates.
                | Group events / capacity windows should not be saved here.
                |--------------------------------------------------------------------------
                */

                Route::put('/booking/rules', [BranchAvailabilityRuleController::class, 'sync'])
                    ->name('booking.rules.update');

                Route::delete('/booking/rules/{rule}', [BranchAvailabilityRuleController::class, 'deleteSeries'])
                    ->name('booking.rules.destroy');

                Route::post('/booking/rules/{rule}/exclude-date', [BranchAvailabilityRuleController::class, 'deleteOccurrence'])
                    ->name('booking.rules.exclude-date');

                Route::post('/booking/rules/{rule}/end-before-date', [BranchAvailabilityRuleController::class, 'deleteFutureOccurrences'])
                    ->name('booking.rules.end-before-date');

                Route::post('/booking/rules/{rule}/reschedule', [BranchAvailabilityRuleController::class, 'reschedule'])
                    ->name('booking.rules.reschedule');


                /*
                |--------------------------------------------------------------------------
                | Disabled days
                |--------------------------------------------------------------------------
                */

                Route::get('/booking/disabled-days', [BranchDisabledDayController::class, 'index'])
                    ->name('booking.disabled-days.index');

                Route::post('/booking/disabled-days', [BranchDisabledDayController::class, 'store'])
                    ->name('booking.disabled-days.store');

                Route::patch('/booking/disabled-days/{disabledDay}', [BranchDisabledDayController::class, 'update'])
                    ->name('booking.disabled-days.update');

                Route::delete('/booking/disabled-days/{disabledDay}', [BranchDisabledDayController::class, 'destroy'])
                    ->name('booking.disabled-days.destroy');


                /*
                |--------------------------------------------------------------------------
                | Bookings
                |--------------------------------------------------------------------------
                */

                Route::post('/booking/bookings', [BranchBookingController::class, 'store'])
                    ->name('booking.bookings.store');

                Route::put('/booking/bookings/{booking}', [BranchBookingController::class, 'update'])
                    ->name('booking.bookings.update');

                Route::post('/booking/bookings/{booking}/cancel', [BranchBookingController::class, 'cancel'])
                    ->name('booking.bookings.cancel');

                Route::post('/booking/bookings/{booking}/reschedule', [BranchBookingController::class, 'reschedule'])
                    ->name('booking.bookings.reschedule');

                Route::post('/booking/bookings/{booking}/duplicate', [BranchBookingController::class, 'duplicate'])
                    ->name('booking.bookings.duplicate');

                /*
                |--------------------------------------------------------------------------
                | Appointment requests
                |--------------------------------------------------------------------------
                */

                Route::post('/booking/appointment-requests/{appointmentRequest}/convert', [
                    BranchBookingCalendarController::class,
                    'convertAppointmentRequest',
                ])->name('booking.appointment-requests.convert');

                Route::delete('/booking/appointment-requests/{appointmentRequest}', [
                    BranchBookingCalendarController::class,
                    'destroyAppointmentRequest',
                ])->name('booking.appointment-requests.destroy');

                /*
                |--------------------------------------------------------------------------
                | Capacity windows / group events
                |
                | These now use {capacityWindow}, not {rule}.
                |--------------------------------------------------------------------------
                */

                Route::post('/booking/capacity-windows', [BranchCapacityWindowController::class, 'store'])
                    ->name('booking.capacity-windows.store');

                Route::put('/booking/capacity-windows/{capacityWindow}', [BranchCapacityWindowController::class, 'update'])
                    ->name('booking.capacity-windows.update');

                Route::post('/booking/capacity-windows/{capacityWindow}/cancel', [BranchCapacityWindowController::class, 'cancel'])
                    ->name('booking.capacity-windows.cancel');

                Route::post('/booking/capacity-windows/{capacityWindow}/reschedule', [BranchCapacityWindowController::class, 'reschedule'])
                    ->name('booking.capacity-windows.reschedule');

                Route::post('/booking/capacity-windows/{capacityWindow}/bookings', [BranchCapacityWindowController::class, 'storeBooking'])
                    ->name('booking.capacity-windows.bookings.store');

                Route::delete('/booking/capacity-windows/{capacityWindow}', [BranchCapacityWindowController::class, 'destroy'])
                    ->name('booking.capacity-windows.destroy');

                Route::delete('/booking/capacity-windows/{capacityWindow}/series', [BranchCapacityWindowController::class, 'destroySeries'])
                    ->name('booking.capacity-windows.destroy-series');

                /*
                |--------------------------------------------------------------------------
                | Temporary compatibility aliases
                |
                | Keep these only while old frontend components still emit the old names.
                | They now still use {capacityWindow}, not {rule}.
                |--------------------------------------------------------------------------
                */

                Route::delete('/booking/capacity-windows/{capacityWindow}/occurrence', [BranchCapacityWindowController::class, 'destroy'])
                    ->name('booking.capacity-windows.delete-occurrence');

                Route::delete('/booking/capacity-windows/{capacityWindow}/from-date', [BranchCapacityWindowController::class, 'destroySeries'])
                    ->name('booking.capacity-windows.delete-from-date');

                Route::delete('/booking/capacity-windows/{capacityWindow}/delete-series', [BranchCapacityWindowController::class, 'destroySeries'])
                    ->name('booking.capacity-windows.delete-series');

                Route::put('/booking/messages/{message}/read', [BranchBookingCalendarController::class, 'markMessageRead'])
                    ->name('booking.messages.read');

                Route::get('/contacts', [BranchController::class, 'settings'])
                    ->name('contacts.page');

                Route::post('/contacts', [BranchContactController::class, 'store'])
                    ->name('contacts.store');

                Route::put('/contacts/{contact}', [BranchContactController::class, 'update'])
                    ->name('contacts.update');

                Route::delete('/contacts/{contact}', [BranchContactController::class, 'destroy'])
                    ->name('contacts.destroy');

                Route::put('/faq-items', [BranchFaqItemController::class, 'update'])
                    ->name('faq-items.update');

                Route::get('/opening-hours', [BranchController::class, 'settings'])
                    ->name('opening-hours.page');

                Route::put('/opening-hours', [BranchOpeningHoursController::class, 'update'])
                    ->name('opening-hours.update');

                Route::get('/opening-hours/pdf', [BranchOpeningHoursPdfController::class, 'show'])
                    ->name('opening-hours.pdf.show');

                Route::get('/opening-hours/pdf/download', [BranchOpeningHoursPdfController::class, 'download'])
                    ->name('opening-hours.pdf.download');

                Route::get('/users', [BranchController::class, 'settings'])
                    ->name('users.page');

                Route::post('/users', [BranchUserController::class, 'store'])
                    ->name('users.store');

                Route::delete('/users/{user}', [BranchUserController::class, 'destroy'])
                    ->name('users.destroy');

                Route::post('/invitations/{branchInvitation}/resend', [BranchUserController::class, 'resendInvitation'])
                    ->name('invitations.resend');

                Route::delete('/invitations/{branchInvitation}', [BranchUserController::class, 'destroyInvitation'])
                    ->name('invitations.destroy');

                Route::get('/employees', [BranchController::class, 'settings'])
                    ->name('employees.page');

                Route::post('/employees', [BranchEmployeeController::class, 'store'])
                    ->name('employees.store');

                Route::put('/employees/{employee}', [BranchEmployeeController::class, 'update'])
                    ->name('employees.update');

                Route::delete('/employees/{employee}', [BranchEmployeeController::class, 'destroy'])
                    ->name('employees.destroy');

                Route::get('/services', [BranchController::class, 'settings'])
                    ->name('services.page');

                Route::post('/services', [BranchServiceController::class, 'store'])
                    ->name('services.store');

                Route::put('/services/{branchService}', [BranchServiceController::class, 'update'])
                    ->name('services.update');

                Route::delete('/services/{branchService}', [BranchServiceController::class, 'destroy'])
                    ->name('services.destroy');

                Route::get('/services/pdf', [BranchServicesPdfController::class, 'show'])
                    ->name('services.pdf.show');

                Route::get('/services/pdf/download', [BranchServicesPdfController::class, 'download'])
                    ->name('services.pdf.download');

                Route::get('/public-site', [BranchController::class, 'settings'])
                    ->name('public-site.page');

                Route::get('/public-site/edit', [BranchController::class, 'settings'])
                    ->name('public-site.edit');

                Route::put('/public-site', [BranchPublicSiteController::class, 'update'])
                    ->name('public-site.update');

                Route::post('/reply-templates', [BranchReplyTemplateController::class, 'store'])
                    ->name('reply-templates.store');

                Route::put('/reply-templates/{replyTemplate}', [BranchReplyTemplateController::class, 'update'])
                    ->name('reply-templates.update');

                Route::delete('/reply-templates/{replyTemplate}', [BranchReplyTemplateController::class, 'destroy'])
                    ->name('reply-templates.destroy');
            });
    });

    Route::middleware('manage.companies')->group(function () {
        Route::resource('services', ServiceController::class)
            ->except(['show']);

        Route::prefix('services/{service}')
            ->name('services.')
            ->group(function () {
                Route::post('/information', [ServiceInformationController::class, 'store'])
                    ->name('information.store');

                Route::put('/information/{information}', [ServiceInformationController::class, 'update'])
                    ->name('information.update');

                Route::delete('/information/{information}', [ServiceInformationController::class, 'destroy'])
                    ->name('information.destroy');

                Route::post('/necessities', [ServiceNecessityController::class, 'store'])
                    ->name('necessities.store');

                Route::put('/necessities/{necessity}', [ServiceNecessityController::class, 'update'])
                    ->name('necessities.update');

                Route::delete('/necessities/{necessity}', [ServiceNecessityController::class, 'destroy'])
                    ->name('necessities.destroy');

                Route::post('/steps', [ServiceStepController::class, 'store'])
                    ->name('steps.store');

                Route::put('/steps/{step}', [ServiceStepController::class, 'update'])
                    ->name('steps.update');

                Route::delete('/steps/{step}', [ServiceStepController::class, 'destroy'])
                    ->name('steps.destroy');

                Route::post('/tags', [ServiceTagController::class, 'store'])
                    ->name('tags.store');

                Route::put('/tags/{tag}', [ServiceTagController::class, 'update'])
                    ->name('tags.update');

                Route::delete('/tags/{tag}', [ServiceTagController::class, 'destroy'])
                    ->name('tags.destroy');

                Route::post('/files', [ServiceFileController::class, 'store'])
                    ->name('files.store');

                Route::put('/files/{file}', [ServiceFileController::class, 'update'])
                    ->name('files.update');

                Route::delete('/files/{file}', [ServiceFileController::class, 'destroy'])
                    ->name('files.destroy');
            });
    });
});

require __DIR__ . '/auth.php';