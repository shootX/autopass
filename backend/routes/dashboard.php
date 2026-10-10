<?php

use App\Http\Controllers\Admin\BodyTypesController;
use App\Http\Controllers\Admin\CorporateController;
use App\Http\Controllers\Admin\CarBrandsController;
use App\Http\Controllers\Admin\PackagesController;
use App\Http\Controllers\Admin\PartnersController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\TicketsController;
use App\Http\Controllers\Admin\VouchersController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Middleware\Authenticate;
use App\Http\Controllers\Admin\ClientsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManagerController;
use App\Http\Controllers\Admin\AppointmentsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\WashingController;

Route::prefix('dashboard')
    ->middleware(Authenticate::class, AdminMiddleware::class)
    ->group(function () {

        Route::get('/logout', function () {
            auth()->logout();
            return redirect()->route('login');
        })->name('admin.logout');

        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/reports/washes', [ReportsController::class, 'washes'])->name('admin.reports.washes');

        Route::get('/search_managers', [WashingController::class, 'search'])->name('admin.search_managers');
        Route::get('/search_wash', [WashingController::class, 'search_wash'])->name('admin.search_wash');
        Route::get('/search_clients', [WashingController::class, 'search_clients'])->name('admin.search_clients');
        Route::get('/search_user_cars', [WashingController::class, 'search_user_cars'])->name('admin.search_clients');
        Route::get('/search_wash_services', [WashingController::class, 'search_wash_services'])->name('admin.search_clients');

        Route::get('/search_car_brands', [ClientsController::class, 'search_car_brands'])->name('admin.search_car_brands');
        Route::get('/search_car_models', [ClientsController::class, 'search_car_models'])->name('admin.search_car_brands');


        Route::post('/set_manager_washing', [WashingController::class, 'set_manager_washing'])->name('admin.set_manager');

        Route::prefix('/clients')->group(function () {

            Route::get('/', [ClientsController::class, 'index'])->name('admin.clients');

            Route::prefix('/{client}')->group(function () {
                Route::get('/ban', [ClientsController::class, 'ban'])->name('admin.clients.ban');
                Route::get('/edit', [ClientsController::class, 'edit'])->name('admin.clients.edit');
                Route::post('/edit_save', [ClientsController::class, 'edit_save'])->name('admin.clients.edit_save');
                Route::get('/delete', [ClientsController::class, 'delete'])->name('admin.clients.delete');
            });

            Route::prefix('/{client}/packages')->group(function () {
                Route::get('/', [ClientsController::class, 'packages'])->name('admin.clients.packages');
                Route::get('/add', [ClientsController::class, 'addUserPackage'])->name('admin.clients.add_package');
                Route::post('/store', [ClientsController::class, 'storeUserPackage'])->name('admin.clients.store_package');
                Route::get('/{pack}/delete', [ClientsController::class, 'packageDelete'])->name('admin.clients.packages.delete');
                Route::post('/renew', [ClientsController::class, 'renewPackage'])->name('admin.clients.renew_package');
            });

            Route::prefix('/{client}/cars')->group(function () {
                Route::get('/', [ClientsController::class, 'cars'])->name('admin.clients.cars');
                Route::get('/add', [ClientsController::class, 'addUserCar'])->name('admin.сlients.add_car');
                Route::post('/store', [ClientsController::class, 'storeUserCar'])->name('admin.clients.store_car');
                Route::get('/{car}/delete', [ClientsController::class, 'carDelete'])->name('admin.clients.packages.delete');
                //Route::post('/renew', [ClientsController::class, 'renewPackage'])->name('admin.clients.renew_package');
            });

            Route::get('/get_packages_for_car/{car}', [ClientsController::class, 'getPackagesForCar'])->name('admin.clients.get_packages_for_car');
        });

        Route::get('/managers', [ManagerController::class, 'index'])->name('admin.managers');

        Route::prefix('/appointments')->group(function () {
            Route::get('/', [AppointmentsController::class, 'index'])->name('admin.appointments');
            Route::get('/add', [AppointmentsController::class, 'addPage'])->name('admin.appointments.add');
            Route::post('/store', [AppointmentsController::class, 'store'])->name('admin.appointments.store');
            Route::get('/{appointment}/delete', [AppointmentsController::class, 'remove'])->name('admin.appointments.delete');
            Route::post('/{appointment}/change_status', [AppointmentsController::class, 'change_status'])->name('admin.appointments.change_status');
        });

        Route::prefix('/washings')->group(function () {
            Route::get('/', [WashingController::class, 'index'])->name('admin.washings');
            Route::get('/add', [WashingController::class, 'addPage'])->name('admin.washings.add');
            Route::get('/{washing}/edit', [WashingController::class, 'editPage'])->name('admin.washings.edit');
            Route::post('/{washing}/edit_save', [WashingController::class, 'edit'])->name('admin.washings.edit_save');
            Route::post('/store', [WashingController::class, 'store'])->name('admin.washings.store');
            Route::get('/{washing}/delete', [WashingController::class, 'remove'])->name('admin.washings.delete');

            Route::get('/{washing}/reviews', [WashingController::class, 'reviews'])->name('admin.washings.reviews');
            Route::get('/{washing}/reviews/{review}/toggle', [WashingController::class, 'toggleReview'])->name('admin.washings.reviews.toggle');
            Route::get('/{washing}/reviews/{review}/delete', [WashingController::class, 'deleteReview'])->name('admin.washings.review_delete');
            Route::get('/{washing}/reviews/add', [WashingController::class, 'addReview'])->name('admin.washings.review_add');
            Route::post('/{washing}/reviews/store', [WashingController::class, 'storeReview'])->name('admin.washings.store_review');
        });

        Route::prefix('/packages')->group(function () {

            Route::get('/', [PackagesController::class, 'index'])->name('admin.packages.index');
            Route::get('/add', [PackagesController::class, 'create'])->name('admin.packages.add');
            Route::get('/{package}/edit', [PackagesController::class, 'edit'])->name('admin.packages.edit');
            Route::post('/{package}/edit', [PackagesController::class, 'edit_save'])->name('admin.packages.edit_save');
            Route::post('/store', [PackagesController::class, 'store'])->name('admin.packages.store');
            Route::get('/{package}/delete', [PackagesController::class, 'delete'])->name('admin.packages.delete');

        });

        Route::prefix('/body-types')->group(function () {
            Route::get('/', [BodyTypesController::class, 'index'])->name('admin.body_types');
            Route::get('/add', [BodyTypesController::class, 'addPage'])->name('admin.body_types.add');
            Route::post('/store', [BodyTypesController::class, 'store'])->name('admin.body_types.store');
            Route::get('/{bodyType}/edit', [BodyTypesController::class, 'edit'])->name('admin.body_types.edit');
            Route::post('/{bodyType}/save', [BodyTypesController::class, 'save'])->name('admin.body_types.save');
            Route::get('/{bodyType}/delete', [BodyTypesController::class, 'delete'])->name('admin.body_types.delete');
        });

        Route::prefix('/brands')->group(function () {
            Route::get('/', [CarBrandsController::class, 'index'])->name('admin.car_brands');
            Route::get('/add', [CarBrandsController::class, 'addPage'])->name('admin.add_car_brand');
            Route::post('/store', [CarBrandsController::class, 'storeCarBrand'])->name('admin.store_car_brand');
            Route::get('/{brand}/delete', [CarBrandsController::class, 'deleteCarBrand'])->name('admin.delete_car_brand');

            Route::prefix('/{brand}/models')->group(function () {
                Route::get('/list', [CarBrandsController::class, 'modelsList'])->name('admin.car_brand_models_list');
                Route::get('/', [CarBrandsController::class, 'modelsIndex'])->name('admin.car_brand_models');
                Route::get('/add', [CarBrandsController::class, 'modelsAddPage'])->name('admin.car_brand_models_add');
                Route::post('/store', [CarBrandsController::class, 'modelStore'])->name('admin.car_brand_models_store');
                Route::get('/{model}/delete', [CarBrandsController::class, 'modelDelete'])->name('admin.car_brand_models_delete');
            });

        });

        Route::prefix('/vouchers')->group(function () {

            Route::prefix('/categories')->group(function(){
                Route::get('/', [VouchersController::class, 'categoriesIndex'])->name('admin.vouchers.categories');
                Route::get('/add', [VouchersController::class, 'categorieAdd'])->name('admin.vouchers.categories.add');
                Route::post('/store', [VouchersController::class, 'categorieStore'])->name('admin.vouchers.categories.store');
                Route::get('/{categorie}/delete', [VouchersController::class, 'categorieDelete'])->name('admin.vouchers.categories.delete');
                Route::get('/{categorie}/edit', [VouchersController::class, 'editPage'])->name('admin.vouchers.categories.edit');
                Route::post('/{categorie}/save', [VouchersController::class, 'save'])->name('admin.vouchers.categories.edit_save');
            });

            Route::get('/', [VouchersController::class, 'index'])->name('admin.vouchers');
            Route::get('/add', [VouchersController::class, 'addPage'])->name('admin.add_vouchers');
            Route::post('/store', [VouchersController::class, 'store'])->name('admin.store_vouchers');
            Route::get('/{voucher}/delete', [VouchersController::class, 'delete'])->name('admin.delete_vouchers');
            Route::get('/{voucher}/edit', [VouchersController::class, 'editPageVoucher'])->name('admin.vouchers.edit');
            Route::post('/{voucher}/save', [VouchersController::class, 'saveVoucher'])->name('admin.vouchers.save');

        });

        Route::prefix('/tickets')->group(function () {

            Route::get('/', [TicketsController::class, 'index'])->name('admin.tickets');
            Route::get('/add', [TicketsController::class, 'addPage'])->name('admin.tickets.add');
            Route::post('/store', [TicketsController::class, 'store'])->name('admin.tickets.store');
            Route::get('/{ticket}/delete', [TicketsController::class, 'delete'])->name('admin.tickets.delete');
            Route::get('/{ticket}/edit', [TicketsController::class, 'edit'])->name('admin.tickets.edit');
            Route::post('/{ticket}/save', [TicketsController::class, 'save'])->name('admin.tickets.save');



            Route::get('/{ticket}/export', [TicketsController::class, 'export'])->name('admin.tickets.export');

        });

        Route::prefix('/corporate')->group(function () {
            Route::get('/', [CorporateController::class, 'index'])->name('admin.corporate');
            Route::get('/add', [CorporateController::class, 'addPage'])->name('admin.corporate.add');
            Route::post('/store', [CorporateController::class, 'store'])->name('admin.corporate.store');
            Route::get('/template', [CorporateController::class, 'template'])->name('admin.corporate.template');
            Route::get('/{corporate}/edit', [CorporateController::class, 'editPage'])->name('admin.corporate.edit');
            Route::post('/{corporate}/save', [CorporateController::class, 'save'])->name('admin.corporate.save');
            Route::get('/{corporate}/delete', [CorporateController::class, 'delete'])->name('admin.corporate.delete');
            Route::get('/{corporate}', [CorporateController::class, 'show'])->name('admin.corporate.show');
            Route::post('/{corporate}/cars', [CorporateController::class, 'storeCar'])->name('admin.corporate.cars.store');
            Route::get('/{corporate}/cars/{car}/delete', [CorporateController::class, 'deleteCar'])->name('admin.corporate.cars.delete');
            Route::post('/{corporate}/import', [CorporateController::class, 'import'])->name('admin.corporate.import');
            Route::post('/{corporate}/token', [CorporateController::class, 'token'])->name('admin.corporate.token');
        });

        Route::prefix('/partners')->group(function () {

            Route::get('/', [PartnersController::class, 'index'])->name('admin.partners');
            Route::get('/add', [PartnersController::class, 'addPage'])->name('admin.partners.add');
            Route::post('/store', [PartnersController::class, 'store'])->name('admin.partners.store');
            Route::get('/{client}/delete', [PartnersController::class, 'delete'])->name('admin.partners.delete');
            Route::get('/{client}/edit', [PartnersController::class, 'editPage'])->name('admin.partners.edit');
            Route::post('/{client}/save', [PartnersController::class, 'editSave'])->name('admin.partners.edit_save');

        });

        Route::prefix('/promo')->group(function () {
            Route::get('/', [PromoController::class, 'index'])->name('admin.promo');
            Route::post('/save', [PromoController::class, 'save'])->name('admin.promo.save');
        });

    });
