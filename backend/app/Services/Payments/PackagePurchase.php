<?php

namespace App\Services\Payments;

use App\Models\Package;
use App\Models\TbcPayment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackagePurchase
{
    public function checkout(User $user, array $data): string
    {
        $this->askAboutClosedPages($user, (int) $data['car_id']);

        $payment = DB::transaction(function () use ($user, $data) {
            // All package reservations for an owner are serialised; no provider request inside this lock.
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $car = $owner->cars()->with('model')->findOrFail($data['car_id']);
            if ($car->carType() === null) {
                throw ValidationException::withMessages(['car_id' => __('cars.body_unconfirmed')]);
            }
            // By id (washes or discount), or as before by its washes count, which names washes packages only.
            $package = isset($data['package_id'])
                ? Package::query()->where('car_type', $car->carType())->findOrFail($data['package_id'])
                : Package::query()->where('car_type', $car->carType())->where('type', Package::TYPE_WASHES)
                    ->where('count_washes', $data['number_of_washes'])->firstOrFail();
            // A discount package has one price, for its 30 days.
            $price = $package->prices()->where('month', $package->isDiscount() ? 1 : $data['sub_term'] ?? 0)->firstOrFail();
            if ($owner->packages()->where('package_id', $package->id)->where('user_car_id', $car->id)
                ->where('end_date', '>', now())->withWashesLeft()->exists()) {
                throw ValidationException::withMessages(['car_id' => __('booking.active_package')]);
            }
            // A closed page Flitt hasn't answered about may have been paid in its last minute: a new page waits for
            // the answer, so the same package isn't bought twice (QA audit 2026-11-04, QA-12).
            if (self::closedPages($owner, (int) $car->id)->where('payload->package_id', $package->id)->exists()) {
                throw ValidationException::withMessages(['car_id' => __('booking.payment_checking')]);
            }
            $pending = TbcPayment::query()->where('user_id', $owner->id)->where('type', 'package')->where('status', 'pending')
                ->where('payload->car_id', $car->id)->where('payload->package_id', $package->id)
                ->where('payload->price_id', $price->id)->where('created_at', '>', now()->subMinutes(TbcPayment::CHECKOUT_MINUTES))
                // Price rows are edited in place: a page opened at an older price is not reused.
                ->where('amount', round((float) $price->price, 2))->get();
            $payload = ['car_id' => $car->id, 'package_id' => $package->id, 'body_type' => $package->car_type, 'price_id' => $price->id,
                'month' => (int) $price->month, 'washes' => (int) $package->count_washes]
                // The discount the driver pays for is the one kept, whatever the admin changes before the payment lands.
                + ($package->isDiscount() ? ['type' => Package::TYPE_DISCOUNT, 'full_price' => (string) $package->full_price,
                    'percent' => (int) $package->percent] : []);
            // Nor one opened before the admin changed the package's terms (discount, full price, washes): the
            // driver pays for what the catalogue shows now. That older page still grants its own terms if paid.
            $same = $pending->first(fn (TbcPayment $open) => self::sameTerms((array) $open->payload, $payload));
            if ($same) {
                return $same;
            }

            return TbcPayment::create([
                'merchant_payment_id' => 'P'.strtoupper(Str::random(16)), 'user_id' => $owner->id,
                'type' => 'package', 'amount' => round((float) $price->price, 2), 'currency' => 'GEL', 'status' => 'pending',
                'payload' => $payload,
            ]);
        });

        return Cache::lock('package-checkout:'.$payment->id, 60)->block(3, function () use ($payment) {
            $current = $payment->fresh();
            if ($current->status !== 'pending') {
                throw ValidationException::withMessages(['car_id' => __('booking.payment_finished')]);
            }
            $url = $current->payload['checkout_url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
            $url = app(FlittCheckout::class)->checkoutUrl($current, 'Autopass package');
            $current->update(['payload' => $current->payload + ['checkout_url' => $url]]);

            return $url;
        });
    }

    /** This driver's package pages for the car that closed while still pending (no answer from Flitt yet). */
    private static function closedPages(User $user, int $carId)
    {
        return TbcPayment::query()->where('user_id', $user->id)->where('type', 'package')->where('status', 'pending')
            ->where('payload->car_id', $carId)
            ->where('created_at', '<=', now()->subMinutes(TbcPayment::CHECKOUT_MINUTES));
    }

    /** Asks Flitt about those pages first: a paid one is granted now, an unpaid one closed, a silent one stays. */
    private function askAboutClosedPages(User $user, int $carId): void
    {
        self::closedPages($user, $carId)->get()->each(function (TbcPayment $payment) {
            try {
                app(SettleTbcPayment::class)->fromFlittOrUnopened($payment, app(FlittCheckout::class));
            } catch (\Throwable $e) {
                Log::warning('package_page_check_failed', ['payment_id' => $payment->id, 'exception' => $e::class]);
            }
        });
    }

    /** @param array<string, mixed> $open @param array<string, mixed> $now */
    private static function sameTerms(array $open, array $now): bool
    {
        foreach (['washes', 'type', 'percent'] as $key) {
            if ((string) ($open[$key] ?? '') !== (string) ($now[$key] ?? '')) {
                return false;
            }
        }

        return round((float) ($open['full_price'] ?? 0), 2) === round((float) ($now['full_price'] ?? 0), 2);
    }
}
