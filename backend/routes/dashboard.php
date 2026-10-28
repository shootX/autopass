<?php

use App\Http\Controllers\Admin\AppContentController;
use App\Http\Controllers\Admin\AppointmentMoveController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\BodyCheckController;
use App\Http\Controllers\Admin\BodyTypesController;
use App\Http\Controllers\Admin\CarBrandsController;
use App\Http\Controllers\Admin\ClientCarBodyController;
use App\Http\Controllers\Admin\ClientPointsController;
use App\Http\Controllers\Admin\ClientProfileController;
use App\Http\Controllers\Admin\ClientsController;
use App\Http\Controllers\Admin\CorporateCarsController;
use App\Http\Controllers\Admin\CorporateController;
use App\Http\Controllers\Admin\CorporateInvoicesController;
use App\Http\Controllers\Admin\CorporatePackagesController;
use App\Http\Controllers\Admin\CorporateWashesController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceSettingsController;
use App\Http\Controllers\Admin\ManagerController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\PartnersController;
use App\Http\Controllers\Admin\PaymentsController;
use App\Http\Controllers\Admin\PointsSettingsController;
use App\Http\Controllers\Admin\PromoCodesController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\PushBroadcastController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\SmsInboxController;
use App\Http\Controllers\Admin\TicketsController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Controllers\Admin\WashingController;
use App\Http\Controllers\FleetCatalogController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuditAdminRequests;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->middleware(Authenticate::class, AdminMiddleware::class, AuditAdminRequests::class)
    ->group(function () {

        Route::post('/logout', function () {
            auth()->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login');
        })->name('admin.logout');

        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/sms-inbox', [SmsInboxController::class, 'index'])->name('admin.sms_inbox');
        Route::get('/sms-inbox/messages', [SmsInboxController::class, 'messages'])->name('admin.sms_inbox.messages');
        Route::get('/reports/washes', [ReportsController::class, 'washes'])->name('admin.reports.washes');

        Route::get('/search_managers', [WashingController::class, 'search'])->name('admin.search_managers');
        Route::get('/search_wash', [WashingController::class, 'search_wash'])->name('admin.search_wash');
        Route::get('/search_clients', [WashingController::class, 'search_clients'])->name('admin.search_clients');
        Route::get('/search_user_cars', [WashingController::class, 'search_user_cars'])->name('admin.search_user_cars');
        Route::get('/search_wash_services', [WashingController::class, 'search_wash_services'])->name('admin.search_wash_services');

        Route::get('/search_car_brands', [ClientsController::class, 'search_car_brands'])->name('admin.search_car_brands');
        Route::get('/search_car_models', [ClientsController::class, 'search_car_models'])->name('admin.search_car_models');

        Route::post('/set_manager_washing', [WashingController::class, 'set_manager_washing'])->name('admin.set_manager');

        Route::prefix('/clients')->group(function () {

            Route::get('/', [ClientsController::class, 'index'])->name('admin.clients');

            Route::prefix('/{client}')->group(function () {
                Route::get('/', [ClientProfileController::class, 'show'])->name('admin.clients.show');
                Route::post('/ban', [ClientsController::class, 'ban'])->name('admin.clients.ban');
                Route::post('/points', [ClientPointsController::class, 'adjust'])->name('admin.clients.points');
                Route::get('/edit', [ClientsController::class, 'edit'])->name('admin.clients.edit');
                Route::post('/edit_save', [ClientsController::class, 'edit_save'])->name('admin.clients.edit_save');
                Route::delete('/delete', [ClientsController::class, 'delete'])->name('admin.clients.delete');
            });

            Route::prefix('/{client}/packages')->group(function () {
                Route::get('/', [ClientsController::class, 'packages'])->name('admin.clients.packages');
                Route::get('/add', [ClientsController::class, 'addUserPackage'])->name('admin.clients.add_package');
                Route::post('/store', [ClientsController::class, 'storeUserPackage'])->name('admin.clients.store_package');
                Route::delete('/{pack}/delete', [ClientsController::class, 'packageDelete'])->name('admin.clients.packages.delete');
                Route::post('/renew', [ClientsController::class, 'renewPackage'])->name('admin.clients.renew_package');
                Route::post('/{pack}/renewal', [ClientPointsController::class, 'renewal'])->name('admin.clients.packages.renewal');
            });

            Route::prefix('/{client}/cars')->group(function () {
                Route::get('/', [ClientsController::class, 'cars'])->name('admin.clients.cars');
                Route::get('/add', [ClientsController::class, 'addUserCar'])->name('admin.сlients.add_car');
                Route::post('/store', [ClientsController::class, 'storeUserCar'])->name('admin.clients.store_car');
                Route::delete('/{car}/delete', [ClientsController::class, 'carDelete'])->name('admin.clients.cars.delete');
                Route::post('/{car}/body-check', [ClientCarBodyController::class, 'check'])->name('admin.clients.cars.body_check');
                Route::post('/{car}/body', [ClientCarBodyController::class, 'confirm'])->name('admin.clients.cars.body');
                // Route::post('/renew', [ClientsController::class, 'renewPackage'])->name('admin.clients.renew_package');
            });

            Route::get('/get_packages_for_car/{car}', [ClientsController::class, 'getPackagesForCar'])->name('admin.clients.get_packages_for_car');
        });

        Route::get('/managers', [ManagerController::class, 'index'])->name('admin.managers');

        Route::prefix('/appointments')->group(function () {
            Route::get('/', [AppointmentsController::class, 'index'])->name('admin.appointments');
            Route::get('/add', [AppointmentsController::class, 'addPage'])->name('admin.appointments.add');
            Route::post('/store', [AppointmentsController::class, 'store'])->name('admin.appointments.store');
            Route::delete('/{appointment}/delete', [AppointmentsController::class, 'remove'])->name('admin.appointments.delete');
            Route::post('/{appointment}/change_status', [AppointmentsController::class, 'change_status'])->name('admin.appointments.change_status');
            Route::post('/{appointment}/move', [AppointmentMoveController::class, 'move'])->name('admin.appointments.move');
        });

        Route::prefix('/washings')->group(function () {
            Route::get('/', [WashingController::class, 'index'])->name('admin.washings');
            Route::get('/add', [WashingController::class, 'addPage'])->name('admin.washings.add');
            Route::get('/{washing}/edit', [WashingController::class, 'editPage'])->name('admin.washings.edit');
            Route::post('/{washing}/edit_save', [WashingController::class, 'edit'])->name('admin.washings.edit_save');
            Route::post('/store', [WashingController::class, 'store'])->name('admin.washings.store');
            Route::delete('/{washing}/delete', [WashingController::class, 'remove'])->name('admin.washings.delete');

            Route::get('/{washing}/reviews', [WashingController::class, 'reviews'])->name('admin.washings.reviews');
            Route::post('/{washing}/reviews/{review}/toggle', [WashingController::class, 'toggleReview'])->name('admin.washings.reviews.toggle');
            Route::delete('/{washing}/reviews/{review}/delete', [WashingController::class, 'deleteReview'])->name('admin.washings.review_delete');
            Route::get('/{washing}/reviews/add', [WashingController::class, 'addReview'])->name('admin.washings.review_add');
            Route::post('/{washing}/reviews/store', [WashingController::class, 'storeReview'])->name('admin.washings.store_review');
        });

        Route::prefix('/packages/corporate')->group(function () {
            Route::get('/', [CorporatePackagesController::class, 'index'])->name('admin.packages.corporate');
            Route::get('/add', [CorporatePackagesController::class, 'create'])->name('admin.packages.corporate.add');
            Route::post('/store', [CorporatePackagesController::class, 'store'])->name('admin.packages.corporate.store');
            Route::get('/{corporatePackage}/edit', [CorporatePackagesController::class, 'edit'])->name('admin.packages.corporate.edit');
            Route::post('/{corporatePackage}/save', [CorporatePackagesController::class, 'save'])->name('admin.packages.corporate.save');
            Route::delete('/{corporatePackage}/delete', [CorporatePackagesController::class, 'delete'])->name('admin.packages.corporate.delete');
        });

        Route::prefix('/packages')->group(function () {

            Route::get('/', [PackagesController::class, 'index'])->name('admin.packages.index');
            Route::get('/add', [PackagesController::class, 'create'])->name('admin.packages.add');
            Route::get('/{package}/edit', [PackagesController::class, 'edit'])->name('admin.packages.edit');
            Route::post('/{package}/edit', [PackagesController::class, 'edit_save'])->name('admin.packages.edit_save');
            Route::post('/store', [PackagesController::class, 'store'])->name('admin.packages.store');
            Route::delete('/{package}/delete', [PackagesController::class, 'delete'])->name('admin.packages.delete');

        });

        Route::prefix('/site')->group(function () {
            Route::get('/settings', [SiteController::class, 'settings'])->name('admin.site.settings');
            Route::post('/settings', [SiteController::class, 'saveSettings'])->name('admin.site.settings.save');
            Route::get('/leads', [SiteController::class, 'leads'])->name('admin.site.leads');
            Route::post('/leads/{lead}/handle', [SiteController::class, 'handleLead'])->name('admin.site.leads.handle');
            Route::delete('/leads/{lead}', [SiteController::class, 'deleteLead'])->name('admin.site.leads.delete');
            Route::get('/{page?}', [SiteController::class, 'edit'])->whereIn('page', ['home', 'business'])->name('admin.site.page');
            Route::post('/{page}', [SiteController::class, 'save'])->whereIn('page', ['home', 'business'])->name('admin.site.save');
            Route::post('/{page}/reset', [SiteController::class, 'reset'])->whereIn('page', ['home', 'business'])->name('admin.site.reset');
        });

        Route::prefix('/body-types')->group(function () {
            Route::get('/', [BodyTypesController::class, 'index'])->name('admin.body_types');
            Route::get('/add', [BodyTypesController::class, 'addPage'])->name('admin.body_types.add');
            Route::post('/store', [BodyTypesController::class, 'store'])->name('admin.body_types.store');
            Route::get('/{bodyType}/edit', [BodyTypesController::class, 'edit'])->name('admin.body_types.edit');
            Route::post('/{bodyType}/save', [BodyTypesController::class, 'save'])->name('admin.body_types.save');
            Route::delete('/{bodyType}/delete', [BodyTypesController::class, 'delete'])->name('admin.body_types.delete');
            Route::post('/check', [BodyCheckController::class, 'run'])->name('admin.body_types.check');
        });

        Route::prefix('/brands')->group(function () {
            Route::get('/', [CarBrandsController::class, 'index'])->name('admin.car_brands');
            Route::get('/add', [CarBrandsController::class, 'addPage'])->name('admin.add_car_brand');
            Route::post('/store', [CarBrandsController::class, 'storeCarBrand'])->name('admin.store_car_brand');
            Route::delete('/{brand}/delete', [CarBrandsController::class, 'deleteCarBrand'])->name('admin.delete_car_brand');
            Route::post('/{brand}/rename', [CarBrandsController::class, 'renameBrand'])->name('admin.car_brand_rename');

            Route::prefix('/{brand}/models')->group(function () {
                Route::get('/list', [CarBrandsController::class, 'modelsList'])->name('admin.car_brand_models_list');
                Route::get('/', [CarBrandsController::class, 'modelsIndex'])->name('admin.car_brand_models');
                Route::get('/add', [CarBrandsController::class, 'modelsAddPage'])->name('admin.car_brand_models_add');
                Route::post('/store', [CarBrandsController::class, 'modelStore'])->name('admin.car_brand_models_store');
                Route::delete('/{model}/delete', [CarBrandsController::class, 'modelDelete'])->name('admin.car_brand_models_delete');
                Route::post('/{model}/save', [CarBrandsController::class, 'modelSave'])->name('admin.car_brand_models_save');
            });

        });

        Route::prefix('/vouchers')->group(function () {

            Route::prefix('/categories')->group(function () {
                Route::get('/', [VouchersController::class, 'categoriesIndex'])->name('admin.vouchers.categories');
                Route::get('/add', [VouchersController::class, 'categorieAdd'])->name('admin.vouchers.categories.add');
                Route::post('/store', [VouchersController::class, 'categorieStore'])->name('admin.vouchers.categories.store');
                Route::delete('/{categorie}/delete', [VouchersController::class, 'categorieDelete'])->name('admin.vouchers.categories.delete');
                Route::get('/{categorie}/edit', [VouchersController::class, 'editPage'])->name('admin.vouchers.categories.edit');
                Route::post('/{categorie}/save', [VouchersController::class, 'save'])->name('admin.vouchers.categories.edit_save');
            });

            Route::get('/', [VouchersController::class, 'index'])->name('admin.vouchers');
            Route::get('/uses', [PaymentsController::class, 'vouchers'])->name('admin.vouchers.uses');
            Route::get('/add', [VouchersController::class, 'addPage'])->name('admin.add_vouchers');
            Route::post('/store', [VouchersController::class, 'store'])->name('admin.store_vouchers');
            Route::delete('/{voucher}/delete', [VouchersController::class, 'delete'])->name('admin.delete_vouchers');
            Route::get('/{voucher}/edit', [VouchersController::class, 'editPageVoucher'])->name('admin.vouchers.edit');
            Route::post('/{voucher}/save', [VouchersController::class, 'saveVoucher'])->name('admin.vouchers.save');

        });

        Route::prefix('/tickets')->group(function () {

            Route::get('/', [TicketsController::class, 'index'])->name('admin.tickets');
            Route::get('/add', [TicketsController::class, 'addPage'])->name('admin.tickets.add');
            Route::post('/store', [TicketsController::class, 'store'])->name('admin.tickets.store');
            Route::delete('/{ticket}/delete', [TicketsController::class, 'delete'])->name('admin.tickets.delete');
            Route::get('/{ticket}/edit', [TicketsController::class, 'edit'])->name('admin.tickets.edit');
            Route::post('/{ticket}/save', [TicketsController::class, 'save'])->name('admin.tickets.save');

            Route::get('/{ticket}/export', [TicketsController::class, 'export'])->name('admin.tickets.export');

        });

        Route::prefix('/corporate')->group(function () {
            Route::get('/', [CorporateController::class, 'index'])->name('admin.corporate');
            Route::get('/add', [CorporateController::class, 'addPage'])->name('admin.corporate.add');
            Route::post('/store', [CorporateController::class, 'store'])->name('admin.corporate.store');
            Route::get('/template', [CorporateController::class, 'template'])->name('admin.corporate.template');
            Route::get('/catalog/brands/{brand}/models', [FleetCatalogController::class, 'models'])->whereNumber('brand');
            Route::get('/catalog/years', [FleetCatalogController::class, 'years']);
            Route::get('/catalog/body-types', [FleetCatalogController::class, 'bodyTypes']);
            Route::get('/invoices', [CorporateInvoicesController::class, 'index'])->name('admin.corporate.invoices');
            Route::get('/invoices/settings', [InvoiceSettingsController::class, 'edit'])->name('admin.corporate.invoices.settings');
            Route::post('/invoices/settings', [InvoiceSettingsController::class, 'save'])->name('admin.corporate.invoices.settings.save');
            Route::post('/invoices/discounts', [CorporateInvoicesController::class, 'discounts'])->name('admin.corporate.invoices.discounts');
            Route::get('/invoices/{invoice}', [CorporateInvoicesController::class, 'show'])->name('admin.corporate.invoices.show');
            Route::post('/invoices/{invoice}/paid', [CorporateInvoicesController::class, 'paid'])->name('admin.corporate.invoices.paid');
            Route::post('/invoices/{invoice}/cancel', [CorporateInvoicesController::class, 'cancel'])->name('admin.corporate.invoices.cancel');
            Route::get('/{corporate}/edit', [CorporateController::class, 'editPage'])->name('admin.corporate.edit');
            Route::post('/{corporate}/save', [CorporateController::class, 'save'])->name('admin.corporate.save');
            Route::post('/{corporate}/ban', [CorporateController::class, 'ban'])->name('admin.corporate.ban');
            Route::delete('/{corporate}/delete', [CorporateController::class, 'delete'])->name('admin.corporate.delete');
            Route::get('/{corporate}', [CorporateController::class, 'show'])->name('admin.corporate.show');
            Route::post('/{corporate}/cars', [CorporateController::class, 'storeCar'])->name('admin.corporate.cars.store');
            Route::delete('/{corporate}/cars/{car}/delete', [CorporateController::class, 'deleteCar'])->name('admin.corporate.cars.delete');
            Route::post('/{corporate}/cars/{car}/driver', [CorporateCarsController::class, 'driver'])->name('admin.corporate.cars.driver');
            Route::post('/{corporate}/cars/{car}/invite', [CorporateCarsController::class, 'invite'])->name('admin.corporate.cars.invite');
            Route::post('/{corporate}/cars/{car}/block', [CorporateCarsController::class, 'block'])->name('admin.corporate.cars.block');
            Route::post('/{corporate}/cars/{car}/mode', [CorporateCarsController::class, 'mode'])->name('admin.corporate.cars.mode');
            Route::post('/{corporate}/cars/{car}/washes', [CorporateCarsController::class, 'washes'])->name('admin.corporate.cars.washes');
            Route::post('/{corporate}/cars/{car}/body-check', [CorporateCarsController::class, 'bodyCheck'])->name('admin.corporate.cars.body_check');
            Route::post('/{corporate}/cars/{car}/body', [CorporateCarsController::class, 'body'])->name('admin.corporate.cars.body');
            Route::post('/{corporate}/invoices', [CorporateInvoicesController::class, 'store'])->name('admin.corporate.invoices.store');
            Route::post('/{corporate}/washes/credit', [CorporateWashesController::class, 'credit'])->name('admin.corporate.washes.credit');
            Route::post('/{corporate}/washes/correct', [CorporateWashesController::class, 'correct'])->name('admin.corporate.washes.correct');
            Route::post('/{corporate}/import', [CorporateController::class, 'import'])->name('admin.corporate.import');
            Route::post('/{corporate}/token', [CorporateController::class, 'token'])->name('admin.corporate.token');
            Route::post('/{corporate}/temporary-password', [CorporateController::class, 'temporaryPassword'])->name('admin.corporate.temporary_password');
        });

        Route::prefix('/partners')->group(function () {

            Route::get('/', [PartnersController::class, 'index'])->name('admin.partners');
            Route::get('/add', [PartnersController::class, 'addPage'])->name('admin.partners.add');
            Route::post('/store', [PartnersController::class, 'store'])->name('admin.partners.store');
            Route::delete('/{client}/delete', [PartnersController::class, 'delete'])->name('admin.partners.delete');
            Route::get('/{client}/edit', [PartnersController::class, 'editPage'])->name('admin.partners.edit');
            Route::post('/{client}/save', [PartnersController::class, 'editSave'])->name('admin.partners.edit_save');
            Route::post('/{client}/ban', [PartnersController::class, 'ban'])->name('admin.partners.ban');
            Route::post('/{client}/temporary-password', [PartnersController::class, 'temporaryPassword'])->name('admin.partners.temporary_password');

        });

        Route::get('/payments', [PaymentsController::class, 'index'])->name('admin.payments');

        Route::prefix('/points')->group(function () {
            Route::get('/', [PointsSettingsController::class, 'index'])->name('admin.points');
            Route::post('/', [PointsSettingsController::class, 'save'])->name('admin.points.save');
            Route::post('/codes', [PromoCodesController::class, 'store'])->name('admin.promo_codes.store');
            Route::post('/codes/{promoCode}/toggle', [PromoCodesController::class, 'toggle'])->name('admin.promo_codes.toggle');
            Route::delete('/codes/{promoCode}', [PromoCodesController::class, 'delete'])->name('admin.promo_codes.delete');
        });

        Route::prefix('/content')->group(function () {
            Route::get('/', [AppContentController::class, 'index'])->name('admin.content');
            Route::post('/faq', [AppContentController::class, 'storeFaq'])->name('admin.content.faq.store');
            Route::post('/faq/{faq}', [AppContentController::class, 'saveFaq'])->name('admin.content.faq.save');
            Route::delete('/faq/{faq}', [AppContentController::class, 'deleteFaq'])->name('admin.content.faq.delete');
            Route::post('/contacts', [AppContentController::class, 'saveContacts'])->name('admin.content.contacts.save');
            Route::post('/services', [AppContentController::class, 'storeService'])->name('admin.content.services.store');
            Route::post('/services/{service}', [AppContentController::class, 'saveService'])->name('admin.content.services.save');
            Route::delete('/services/{service}', [AppContentController::class, 'deleteService'])->name('admin.content.services.delete');
        });

        Route::get('/push', [PushBroadcastController::class, 'index'])->name('admin.push');
        Route::post('/push', [PushBroadcastController::class, 'send'])->middleware('throttle:5,1')->name('admin.push.send');
        Route::post('/push/settings', [PushBroadcastController::class, 'settings'])->name('admin.push.settings');

        Route::prefix('/promo')->group(function () {
            Route::get('/', [PromoController::class, 'index'])->name('admin.promo');
            Route::post('/save', [PromoController::class, 'save'])->name('admin.promo.save');
        });

    });
