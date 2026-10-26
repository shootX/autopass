<?php

use App\Http\Controllers\Admin\SmsInboxController;
use App\Http\Controllers\Api\AppointmentsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarCatalogController;
use App\Http\Controllers\Api\CarsController;
use App\Http\Controllers\Api\ContactsController;
use App\Http\Controllers\Api\CorporateFleetController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PackagesController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\PointsController;
use App\Http\Controllers\Api\ReferralsController;
use App\Http\Controllers\Api\ReviewsController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\TicketsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VouchersController;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\EnsureApiSession;
use App\Http\Middleware\EnsurePartnerReady;
use App\Models\Promo;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{lang}', function ($lang) {

    if ($lang === 'ge') {
        $lang = 'ka';
    }

    $allowed = ['ka', 'en', 'ru'];
    if (! in_array($lang, $allowed, true)) {
        return response()->json(['success' => false], 404);
    }

    $jsonFile = file_get_contents(public_path('lang/'.$lang.'.json'));
    $etag = '"'.md5($jsonFile).'"';

    if (request()->header('If-None-Match') === $etag) {
        return response('', 304)->header('ETag', $etag);
    }

    return response($jsonFile, 200)
        ->header('Content-Type', 'application/json')
        ->header('Cache-Control', 'no-cache')
        ->header('ETag', $etag);

});

Route::get('/ref/ip', [ReferralsController::class, 'getIp']);
Route::post('/ref/save-hash', [ReferralsController::class, 'saveHash'])->middleware('throttle:referral');
Route::post('/ref/check-hash', [ReferralsController::class, 'checkHash'])->middleware('throttle:referral');

Route::post('/device/register', [AuthController::class, 'registerDevice']);

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:otp-send');
Route::post('/register/verify', [AuthController::class, 'verify'])->middleware('throttle:otp');
Route::post('/register/set_password', [AuthController::class, 'set_password'])->middleware('throttle:otp');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
// Public: works with the httpOnly refresh cookie after the access token expired (legacy: valid bearer).
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh');
// Public logout by the refresh cookie (under its Path), works with an expired access token.
Route::post('/refresh/logout', [AuthController::class, 'logoutByCookie'])->middleware('throttle:refresh');

Route::post('/change_password', [AuthController::class, 'changePassword'])->middleware('throttle:otp-send');
Route::post('/change_password/verify', [AuthController::class, 'changePasswordVerify'])->middleware('throttle:otp');
Route::post('/change_password/verify_submit', [AuthController::class, 'changePasswordVerifySubmit'])->middleware('throttle:otp');

// Public: image views send no token. Only ids the server already knows reach geoapi.ge (CarTrims::photo).
Route::get('/cars/models/{model_id}/photo', [CarCatalogController::class, 'photo'])
    ->whereNumber('model_id')->middleware('throttle:120,1');

Route::middleware(['auth:api', CorsMiddleware::class, EnsureApiSession::class])->group(function () {

    Route::post('/device/push', [AuthController::class, 'setDevicePushId']);

    Route::post('/account/delete', [AuthController::class, 'deleteAccount']);

    Route::post('/change_phone', [AuthController::class, 'changePhone'])->middleware('throttle:otp-send');
    Route::post('/change_phone/verify', [AuthController::class, 'changePhoneVerify'])->middleware('throttle:otp');

    Route::post('/email/set', [UserController::class, 'emailSet'])->middleware('throttle:otp');
    Route::post('/email/verify', [UserController::class, 'emailVerify'])->middleware('throttle:otp');

    Route::post('/push_settings', [UserController::class, 'pushSettings']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [UserController::class, 'me']);
    Route::get('/me/get-referral-link', function () {
        return response()->json(['success' => true, 'referral_link' => auth()->user()->getReferralCode()]);
    });
    Route::post('/me/edit', [UserController::class, 'edit']);

    Route::get('/transactions', [UserController::class, 'transactions']);

    Route::get('/cars/brands', [CarsController::class, 'brands']);
    Route::get('/cars/years', [CarsController::class, 'years']);
    Route::get('/cars/brands/{brand_id}/models', [CarsController::class, 'models']);
    Route::get('/cars/models/{model_id}/generations', [CarCatalogController::class, 'generations'])->whereNumber('model_id');
    Route::get('/cars/models/{model_id}/generations/{generation_id}/modifications', [CarCatalogController::class, 'modifications'])
        ->whereNumber(['model_id', 'generation_id']);
    Route::get('/cars/modifications/{id}', [CarCatalogController::class, 'modification'])->whereNumber('id');

    Route::prefix('/mycars')->group(function () {
        Route::get('/', [CarsController::class, 'mycars']);
        Route::post('/add', [CarsController::class, 'addMyCar']);
        Route::get('/{carid}', [CarsController::class, 'showMyCar']);
        Route::post('/{carid}/edit', [CarsController::class, 'editMyCar']);
        Route::delete('/{carid}/remove', [CarsController::class, 'removeMyCar']);
    });

    Route::prefix('/tickets')->group(function () {
        Route::get('/', [TicketsController::class, 'getTickets']);
        Route::post('/buy', [TicketsController::class, 'buyTicket']);
        Route::get('/my', [TicketsController::class, 'myTickets']);
    });

    Route::get('/shop/home', [ShopController::class, 'home']);

    Route::prefix('/vouchers')->group(function () {
        Route::get('/', [VouchersController::class, 'getVouchers']);
        Route::get('/categories', [VouchersController::class, 'getCategories']);
        Route::post('/buy', [VouchersController::class, 'buyVoucher'])->middleware('throttle:voucher');
        Route::get('/my', [VouchersController::class, 'myVouchers']);
        Route::get('/history', [VouchersController::class, 'history']);
    });

    Route::prefix('/myreviews')->group(function () {
        Route::get('/', [ReviewsController::class, 'myReviews']);
        Route::post('/add', [ReviewsController::class, 'addReview']);
    });

    Route::get('/faq', [FaqController::class, 'faq']);
    Route::get('/contacts', [ContactsController::class, 'contacts']);

    Route::get('/branches', [AppointmentsController::class, 'branches']);
    Route::get('/services-list', [AppointmentsController::class, 'servicesList']);

    Route::get('/myappointments', [AppointmentsController::class, 'myAppointments']);

    Route::get('/checkqr/{qr}', [AppointmentsController::class, 'checkQr'])->middleware('throttle:30,1');

    Route::prefix('/packages')->group(function () {
        Route::get('/', [PackagesController::class, 'packages']);
        Route::post('/buy', [PackagesController::class, 'buyPackage']);
        Route::get('/my', [PackagesController::class, 'myPackages']);
        Route::get('/{id}/qr', [PackagesController::class, 'qrCode']);
        Route::delete('/{userPackage}/remove', [PackagesController::class, 'removePackage']);
    });

    Route::prefix('appointments')->group(function () {

        Route::post('/{appointment}/edit', [AppointmentsController::class, 'setStatus']);
        Route::post('/add', [AppointmentsController::class, 'addAppointment']);
        Route::delete('/{id}/remove', [AppointmentsController::class, 'removeAppointment']);

    });

    Route::prefix('referrals')->group(function () {
        Route::match(['get', 'post'], '/list', [ReferralsController::class, 'list']);
    });

    Route::prefix('points')->group(function () {
        Route::post('/buy', [PointsController::class, 'buy']);
    });

    Route::post('/package/{id}/approve', [PackagesController::class, 'managerApprove'])->middleware('throttle:30,1');

    Route::get('/manager/my-washes', [StatisticsController::class, 'my_washes']);
    Route::get('/manager/report', [StatisticsController::class, 'report']);
    Route::get('/washes/{carWash}/stats', [StatisticsController::class, 'statistic']);

    Route::get('/allrecords', [AppointmentsController::class, 'allRecords']);

    // Gated in the controller: off in production unless TEST_PAYMENT_ALLOW_PRODUCTION=true.
    Route::get('/testpay', [PaymentsController::class, 'testpay']);

    Route::get('/admin/sms-inbox', [SmsInboxController::class, 'messages']);

});

Route::middleware('throttle:public')->get('/promo', function () {
    // Public sidebar banner: expose only the display fields.
    $promo = Promo::query()->select(['title', 'description', 'url'])->first();

    return response()->json(['success' => true, 'promo' => $promo]);
});

Route::prefix('/corporate')->middleware('throttle:corporate-api')->group(function () {
    Route::get('/vehicles', [CorporateFleetController::class, 'index']);
    Route::post('/vehicles', [CorporateFleetController::class, 'store']);
    Route::patch('/vehicles/{plate}', [CorporateFleetController::class, 'update']);
    Route::delete('/vehicles/{plate}', [CorporateFleetController::class, 'destroy']);
});

Route::prefix('/partner')->middleware([CorsMiddleware::class])->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\Partners\AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('partner.api.login');

    Route::middleware(['auth:partners', EnsurePartnerReady::class])->group(function () {
        Route::post('/logout', [App\Http\Controllers\Api\Partners\AuthController::class, 'logout'])->name('partner.api.logout');
        Route::post('/password', [App\Http\Controllers\Api\Partners\AuthController::class, 'password'])->name('partner.api.password');
        Route::middleware('throttle:voucher')->group(function () {
            Route::post('/voucher/check', [App\Http\Controllers\Api\Partners\VouchersController::class, 'checkVoucher'])->name('partner.api.voucher.check');
            Route::post('/voucher/use', [App\Http\Controllers\Api\Partners\VouchersController::class, 'useVoucher'])->name('partner.api.voucher.use');
        });
    });
});
