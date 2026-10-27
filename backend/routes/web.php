<?php

use App\Http\Controllers\Api\ReferralsController;
use App\Http\Controllers\FleetCatalogController;
use App\Http\Controllers\PartnerFleetController;
use App\Http\Controllers\PartnerInvoicesController;
use App\Http\Controllers\PartnerPanelController;
use App\Http\Controllers\PartnerWashesController;
use App\Http\Controllers\WebHook\FlittWebHookController;
use App\Http\Controllers\WebHook\TbcCheckoutController;
use App\Http\Middleware\CorporatePanel;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::prefix('partner')->group(function () {
    Route::get('/login', [PartnerPanelController::class, 'loginPage'])->name('partner.login');
    Route::post('/login', [PartnerPanelController::class, 'login'])->middleware('throttle:10,1')->name('partner.login.submit');

    Route::middleware(CorporatePanel::class)->group(function () {
        Route::get('/password', [PartnerPanelController::class, 'password'])->name('partner.password');
        Route::post('/password', [PartnerPanelController::class, 'savePassword'])->name('partner.password.save');
        Route::get('/', [PartnerPanelController::class, 'dashboard'])->name('partner.home');
        Route::get('/fleet', [PartnerPanelController::class, 'home'])->name('partner.fleet');
        Route::get('/reports', [PartnerPanelController::class, 'reports'])->name('partner.reports');
        Route::post('/logout', [PartnerPanelController::class, 'logout'])->name('partner.logout');
        Route::get('/account', [PartnerPanelController::class, 'account'])->name('partner.account');
        Route::post('/account', [PartnerPanelController::class, 'saveAccount'])->name('partner.account.save');
        Route::post('/cars', [PartnerPanelController::class, 'storeCar'])->name('partner.cars.store');
        Route::delete('/cars/{car}/delete', [PartnerPanelController::class, 'deleteCar'])->name('partner.cars.delete');
        Route::post('/cars/{car}/driver', [PartnerFleetController::class, 'driver'])->name('partner.cars.driver');
        Route::post('/cars/{car}/invite', [PartnerFleetController::class, 'invite'])->middleware('throttle:20,1')->name('partner.cars.invite');
        Route::post('/cars/{car}/block', [PartnerFleetController::class, 'block'])->name('partner.cars.block');
        Route::post('/cars/{car}/mode', [PartnerFleetController::class, 'mode'])->name('partner.cars.mode');
        Route::post('/cars/{car}/washes', [PartnerFleetController::class, 'washes'])->name('partner.cars.washes');
        Route::post('/cars/{car}/body-check', [PartnerFleetController::class, 'bodyCheck'])->middleware('throttle:20,1')->name('partner.cars.body_check');
        Route::get('/washes', [PartnerWashesController::class, 'index'])->name('partner.washes');
        Route::get('/invoices', [PartnerInvoicesController::class, 'index'])->name('partner.invoices');
        Route::post('/invoices', [PartnerInvoicesController::class, 'store'])->middleware('throttle:10,1')->name('partner.invoices.store');
        Route::get('/invoices/{invoice}', [PartnerInvoicesController::class, 'show'])->name('partner.invoices.show');
        Route::post('/invoices/{invoice}/cancel', [PartnerInvoicesController::class, 'cancel'])->name('partner.invoices.cancel');
        Route::post('/import', [PartnerPanelController::class, 'import'])->name('partner.import');
        Route::get('/catalog/brands/{brand}/models', [FleetCatalogController::class, 'models'])->whereNumber('brand')->middleware('throttle:60,1');
        Route::get('/catalog/years', [FleetCatalogController::class, 'years'])->middleware('throttle:60,1');
        Route::get('/catalog/body-types', [FleetCatalogController::class, 'bodyTypes'])->middleware('throttle:60,1');
        Route::get('/template', [PartnerPanelController::class, 'template'])->name('partner.template');
        Route::post('/token', [PartnerPanelController::class, 'token'])->name('partner.token');
    });
});

Route::get('/ref/{code}', [ReferralsController::class, 'handleRef'])->name('referral.link');

// Gated in the controller: off in production unless TEST_PAYMENT_ALLOW_PRODUCTION=true.
Route::get('/payment/sandbox', [TbcCheckoutController::class, 'sandbox'])
    ->name('payment.sandbox');
Route::post('/payment/sandbox', [TbcCheckoutController::class, 'sandboxConfirm'])
    ->name('payment.sandbox.confirm');

Route::any('/payment/return', [TbcCheckoutController::class, 'returned'])
    ->name('payment.return');

Route::any('/callback/tbc/payment', [TbcCheckoutController::class, 'callback'])
    ->name('callback.tbc.payment');

Route::any('/callback/flitt/payment', [FlittWebHookController::class, 'callback'])
    ->name('callback.flitt.payment');
