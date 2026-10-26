<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Services\FleetCars;
use App\Services\FleetInvites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CorporateFleetController extends Controller
{
    public function index(Request $request)
    {
        $client = $this->client($request);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $cars = $client->cars()->orderBy('plate')->get(['plate', 'brand', 'model', 'status', 'driver_phone'])
            ->map(fn (FleetCar $car) => [
                'plate' => $car->plate,
                'brand' => $car->brand,
                'model' => $car->model,
                'status' => $car->status,
                'phone' => $car->driver_phone,
            ]);

        return response()->json(['ok' => true, 'vehicles' => $cars]);
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

    /** PATCH /vehicles/{plate}: {"phone": "5xx xx xx xx"} invites a new driver, {"blocked": true|false} toggles the car. */
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
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        $car->setRelation('client', $client);
        $invite = null;
        if ($request->has('phone')) {
            $invite = FleetInvites::setDriver($car, (string) $request->input('phone'));
            if ($invite === 'phone_invalid') {
                return response()->json(['ok' => false, 'message' => 'Invalid phone'], 422);
            }
        }
        if ($request->has('blocked')) {
            FleetInvites::setBlocked($car, $request->boolean('blocked'));
        }

        return response()->json(['ok' => true, 'status' => $car->status, 'invite' => $invite]);
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

        $car->delete();

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
