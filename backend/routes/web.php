<?php

use App\Http\Controllers\Api\ReferralsController;
use App\Http\Controllers\WebHook\FlittWebHookController;
use App\Jobs\SendPush;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::get('/ref/{code}', [ReferralsController::class, 'handleRef'])->name('referral.link');

Route::any('/payment/return', function(){
    return redirect('https://app.geocar.ge/');
})->name('payment.return');

Route::any('/callback/flitt/payment', [FlittWebHookController::class, 'callback'])
    ->name('callback.flitt.payment');

