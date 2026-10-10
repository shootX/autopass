<?php

use App\Http\Controllers\Api\ReferralsController;
use App\Http\Controllers\PartnerPanelController;
use App\Http\Middleware\CorporatePanel;
use App\Http\Controllers\WebHook\FlittWebHookController;
use App\Http\Controllers\WebHook\TbcCheckoutController;
use App\Jobs\SendPush;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::prefix('partner')->group(function () {
    Route::get('/login', [PartnerPanelController::class, 'loginPage'])->name('partner.login');
    Route::post('/login', [PartnerPanelController::class, 'login'])->middleware('throttle:10,1')->name('partner.login.submit');

    Route::middleware(CorporatePanel::class)->group(function () {
        Route::get('/', [PartnerPanelController::class, 'dashboard'])->name('partner.home');
        Route::get('/fleet', [PartnerPanelController::class, 'home'])->name('partner.fleet');
        Route::get('/reports', [PartnerPanelController::class, 'reports'])->name('partner.reports');
        Route::post('/logout', [PartnerPanelController::class, 'logout'])->name('partner.logout');
        Route::get('/account', [PartnerPanelController::class, 'account'])->name('partner.account');
        Route::post('/account', [PartnerPanelController::class, 'saveAccount'])->name('partner.account.save');
        Route::post('/cars', [PartnerPanelController::class, 'storeCar'])->name('partner.cars.store');
        Route::get('/cars/{car}/delete', [PartnerPanelController::class, 'deleteCar'])->name('partner.cars.delete');
        Route::post('/import', [PartnerPanelController::class, 'import'])->name('partner.import');
        Route::get('/template', [PartnerPanelController::class, 'template'])->name('partner.template');
        Route::post('/token', [PartnerPanelController::class, 'token'])->name('partner.token');
    });
});

Route::get('/ref/{code}', [ReferralsController::class, 'handleRef'])->name('referral.link');

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

