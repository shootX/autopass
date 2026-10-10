<?php

use App\Http\Controllers\Api\AppointmentsController;
use App\Http\Controllers\Api\CorporateFleetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarsController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\PackagesController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\PointsController;
use App\Http\Controllers\Api\ReferralsController;
use App\Http\Controllers\Api\ReviewsController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\TicketsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ContactsController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\VouchersController;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CorsMiddleware;

Route::get('/lang/{lang}', function ($lang) {

    if ($lang === 'ge') {
        $lang = 'ka';
    }

    $allowed = ['ka', 'en', 'ru'];
    if (!in_array($lang, $allowed, true)) {
        return response()->json(['success' => false], 404);
    }

    $jsonFile = file_get_contents(public_path('lang/' . $lang . '.json'));
    $etag = '"' . md5($jsonFile) . '"';

    if (request()->header('If-None-Match') === $etag) {
        return response('', 304)->header('ETag', $etag);
    }

    return response($jsonFile, 200)
        ->header('Content-Type', 'application/json')
        ->header('Cache-Control', 'no-cache')
        ->header('ETag', $etag);

});

Route::get('/ref/ip', [ReferralsController::class, 'getIp']);
Route::post('/ref/save-hash', [ReferralsController::class, 'saveHash']);
Route::post('/ref/check-hash', [ReferralsController::class, 'checkHash']);

Route::post('/device/register', [AuthController::class, 'registerDevice']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/verify', [AuthController::class, 'verify']);
Route::post('/register/set_password', [AuthController::class, 'set_password']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/change_password', [AuthController::class, 'changePassword']);
Route::post('/change_password/verify', [AuthController::class, 'changePasswordVerify']);
Route::post('/change_password/verify_submit', [AuthController::class, 'changePasswordVerifySubmit']);

Route::middleware(['auth:api', CorsMiddleware::class, \App\Http\Middleware\EnsureApiSession::class])->group(function () {

    Route::post('/device/push', [AuthController::class, 'setDevicePushId']);

    Route::post('/account/delete', [AuthController::class, 'deleteAccount']);

    Route::post('/change_phone', [AuthController::class, 'changePhone']);
    Route::post('/change_phone/verify', [AuthController::class, 'changePhoneVerify']);

    Route::post('/email/set', [UserController::class, 'emailSet']);
    Route::post('/email/verify', [UserController::class, 'emailVerify']);

    Route::post('/push_settings', [UserController::class, 'pushSettings']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', [UserController::class, 'me']);
    Route::get('/me/get-referral-link', function (){
        return response()->json(['success' => true, 'referral_link' => auth()->user()->getReferralCode()]);
    });
    Route::post('/me/edit', [UserController::class, 'edit']);

    Route::get('/transactions', [UserController::class, 'transactions']);

    Route::get('/cars/brands', [CarsController::class, 'brands']);
    Route::get('/cars/brands/{brand_id}/models', [CarsController::class, 'models']);

    Route::prefix('/mycars')->group(function () {
        Route::get('/', [CarsController::class, 'mycars']);
        Route::post('/add', [CarsController::class, 'addMyCar']);
        Route::post('/{carid}/edit', [CarsController::class, 'editMyCar']);
        Route::delete('/{carid}/remove', [CarsController::class, 'removeMyCar']);
    });

    Route::prefix('/tickets')->group(function(){
        Route::get('/', [TicketsController::class, 'getTickets']);
        Route::post('/buy', [TicketsController::class, 'buyTicket']);
        Route::get('/my', [TicketsController::class, 'myTickets']);
    });

    Route::get('/shop/home', [ShopController::class, 'home']);

    Route::prefix('/vouchers')->group(function () {
        Route::get('/', [VouchersController::class, 'getVouchers']);
        Route::get('/categories', [VouchersController::class, 'getCategories']);
        Route::post('/buy', [VouchersController::class, 'buyVoucher']);
        Route::get('/my', [VouchersController::class, 'myVouchers']);
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

    Route::get('/checkqr/{qr}', [AppointmentsController::class, 'checkQr']);

    Route::prefix('/packages')->group(function () {
        Route::get('/', [PackagesController::class, 'packages']);
        Route::post('/buy', [PackagesController::class, 'buyPackage']);
        Route::get('/my', [PackagesController::class, 'myPackages']);
        Route::get('/{id}/qr', [PackagesController::class, 'qrCode']);
        Route::delete('/{userPackage}/remove', [PackagesController::class, 'removePackage']);
    });

    Route::prefix('appointments')->group(function (){

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

    Route::post('/package/{id}/approve', [PackagesController::class, 'managerApprove']);

    Route::get('/manager/my-washes', [StatisticsController::class, 'my_washes']);
    Route::get('/manager/report', [StatisticsController::class, 'report']);
    Route::get('/washes/{carWash}/stats', [StatisticsController::class, 'statistic']);

    Route::get('/allrecords', [AppointmentsController::class, 'allRecords']);


    Route::get('/testpay', [PaymentsController::class, 'testpay']);

    Route::get('/admin/sms-inbox', [\App\Http\Controllers\Admin\SmsInboxController::class, 'messages']);

});

Route::get('/promo', function(){
    $promo = \App\Models\Promo::query()->first();
    return response()->json(['success' => true, 'promo' => $promo]);
});

Route::prefix('/corporate')->group(function () {
    Route::get('/vehicles', [CorporateFleetController::class, 'index']);
    Route::post('/vehicles', [CorporateFleetController::class, 'store']);
    Route::delete('/vehicles/{plate}', [CorporateFleetController::class, 'destroy']);
});

Route::prefix('/partner')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\Partners\AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('partner.api.login');

    Route::middleware(['auth:partners', CorsMiddleware::class, \App\Http\Middleware\EnsurePartnerReady::class])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\Partners\AuthController::class, 'logout'])->name('partner.api.logout');
        Route::post('/password', [\App\Http\Controllers\Api\Partners\AuthController::class, 'password'])->name('partner.api.password');
        Route::post('/voucher/check', [\App\Http\Controllers\Api\Partners\VouchersController::class, 'checkVoucher'])->name('partner.api.voucher.check');
        Route::post('/voucher/use', [\App\Http\Controllers\Api\Partners\VouchersController::class, 'useVoucher'])->name('partner.api.voucher.use');
    });
});
