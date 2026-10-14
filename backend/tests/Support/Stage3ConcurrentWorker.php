<?php

use App\Models\TbcPayment;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Payments\FlittCheckout;
use App\Services\Payments\PackagePurchase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Http::preventStrayRequests();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    if ($input['mode'] === 'booking') {
        $result = app(BookingService::class)->create($input['user_id'], $input['data']);
        echo json_encode(['ok' => true, 'id' => $result->id], JSON_THROW_ON_ERROR);
    } else {
        app()->instance(FlittCheckout::class, new class extends FlittCheckout
        {
            public function checkoutUrl(TbcPayment $payment, string $description): string
            {
                return 'https://checkout.example.test/'.$payment->merchant_payment_id;
            }
        });
        $url = app(PackagePurchase::class)->checkout(User::findOrFail($input['user_id']), $input['data']);
        echo json_encode(['ok' => true, 'url' => $url], JSON_THROW_ON_ERROR);
    }
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'validation' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage());
    exit(1);
}
