<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Services\CorporateDiscounts;
use App\Services\CorporateWashes;
use App\Services\FleetCars;
use App\Services\FleetInvites;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CorporateFleetController extends Controller
{
    public function index(Request $request)
    {
        $client = $this->client($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $cars = $client->cars()->orderBy('plate')->get(['plate', 'brand', 'model', 'status', 'driver_phone', 'mode', 'corporate_package_id', 'washes_left'])
            ->map(fn (FleetCar $car) => [
                'plate' => $car->plate,
                'brand' => $car->brand,
                'model' => $car->model,
                'status' => $car->status,
                'phone' => $car->driver_phone,
                'mode' => $car->mode,
                'discount_package_id' => $car->corporate_package_id,
                'washes' => (int) $car->washes_left,
            ]);

        return response()->json(['ok' => true, 'vehicles' => $cars]);
    }

    /** GET /balance: the company's washes, how many are given to cars and how many are free. */
    public function balance(Request $request)
    {
        $client = $this->client($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        return response()->json(['ok' => true] + CorporateWashes::balance($client));
    }

    /** GET /discounts: the company's active discount packages; PATCH a car with `discount_package_id` to use one. */
    public function discounts(Request $request)
    {
        $client = $this->client($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $packages = $client->discountPackages()->with('prices')->get()->map(fn ($package) => [
            'id' => (int) $package->id,
            'title' => $package->title,
            'monthly_limit' => $package->monthly_limit,
            'prices' => $package->prices->map(fn ($row) => CorporateDiscounts::payload($row))->values(),
        ]);

        return response()->json(['ok' => true, 'discount_packages' => $packages]);
    }

    public function store(Request $request)
    {
        $client = $this->client($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $payload = $request->input('vehicles');
        if (! is_array($payload)) {
            $payload = [[
                'plate' => $request->input('plate'),
                'brand' => $request->input('brand'),
                'model' => $request->input('model'),
                'phone' => $request->input('phone'),
            ]];
        }

        if (count($payload) > 500) {
            return response()->json(['ok' => false, 'message' => 'Too many vehicles'], 422);
        }

        $added = 0;
        $skipped = [];
        foreach ($payload as $row) {
            if (! is_array($row)) {
                $skipped[] = ['plate' => '', 'reason' => 'invalid'];

                continue;
            }
            $plate = (string) ($row['plate'] ?? '');
            $phone = isset($row['phone']) && is_scalar($row['phone']) ? (string) $row['phone'] : null;
            $result = FleetCars::add($client, $plate, (string) ($row['brand'] ?? ''), (string) ($row['model'] ?? ''), 'api', $phone);
            if ($result === 'added') {
                $added++;
            } else {
                $skipped[] = ['plate' => FleetCars::plate($plate), 'reason' => $result];
            }
        }

        return response()->json(['ok' => true, 'added' => $added, 'skipped' => $skipped]);
    }

    /**
     * PATCH /vehicles/{plate}: {"phone": "5xx xx xx xx"} invites a new driver, {"blocked": true|false} toggles
     * the car, {"mode": "washes"|"discount"} picks how it washes, {"washes": 4} sets its share of the pool.
     */
    public function update(Request $request, string $plate)
    {
        $client = $this->client($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $car = $client->cars()->where('plate', FleetCars::plate($plate))->first();
        if (! $car) {
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'phone' => ['sometimes', 'required', 'string', 'max:32'],
            'blocked' => ['sometimes', 'boolean'],
            'mode' => ['sometimes', 'string', Rule::in(FleetCar::MODES)],
            'discount_package_id' => ['sometimes', 'integer'],
            'washes' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        // Checks that can fail run first, so a refused request changes nothing.
        if ($request->has('phone') && Phone::normalize(trim((string) $request->input('phone'))) === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid phone'], 422);
        }
        // A discount package puts the car in discount mode at that package.
        $package = null;
        if ($request->has('discount_package_id')) {
            $package = CorporateDiscounts::find($client, $request->integer('discount_package_id'));
            if (! $package || $request->input('mode', FleetCar::MODE_DISCOUNT) !== FleetCar::MODE_DISCOUNT) {
                return response()->json(['ok' => false, 'message' => 'Unknown discount package'], 422);
            }
        }
        if ($request->has('washes')
            && CorporateWashes::allocate($car, $request->integer('washes'), 'api') === 'not_enough') {
            return response()->json(['ok' => false, 'message' => 'Not enough free washes', 'free' => CorporateWashes::balance($client)['free']], 422);
        }

        $car->setRelation('client', $client);
        $invite = null;
        if ($request->has('phone')) {
            $invite = FleetInvites::setDriver($car, (string) $request->input('phone'));
        }
        if ($request->has('blocked')) {
            FleetInvites::setBlocked($car, $request->boolean('blocked'));
        }
        if ($request->has('mode') || $package) {
            CorporateWashes::setMode($car, (string) $request->input('mode', FleetCar::MODE_DISCOUNT), $package);
        }

        return response()->json([
            'ok' => true,
            'status' => $car->status,
            'invite' => $invite,
            'mode' => $car->mode,
            'discount_package_id' => $car->corporate_package_id,
            'washes' => (int) $car->washes_left,
        ]);
    }

    public function destroy(Request $request, string $plate)
    {
        $client = $this->client($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $car = FleetCar::query()
            ->where('corporate_client_id', $client->id)
            ->where('plate', FleetCars::plate($plate))
            ->first();

        if (! $car) {
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        CorporateWashes::deleteCar($car, 'api');

        return response()->json(['ok' => true]);
    }

    private function client(Request $request): CorporateClient|JsonResponse
    {
        $key = $request->bearerToken() ?: $request->header('X-Api-Key');
        if (! $key) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $client = CorporateClient::findByToken((string) $key);
        if (! $client) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        if (! $client->api_token_used_at || $client->api_token_used_at->lt(now()->subMinutes(5))) {
            $client->forceFill(['api_token_used_at' => now()])->saveQuietly();
        }

        if ($client->ban) {
            return response()->json(['ok' => false, 'message' => 'Account is blocked'], 403);
        }

        if ($client->password_must_change) {
            return response()->json(['ok' => false, 'message' => 'Password change required'], 403);
        }

        return $client;
    }
}
