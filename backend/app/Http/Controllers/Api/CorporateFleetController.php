<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use App\Services\FleetCars;
use Illuminate\Http\Request;

class CorporateFleetController extends Controller
{
    public function index(Request $request)
    {
        $client = $this->client($request);
        if ($client instanceof \Illuminate\Http\JsonResponse) {
            return $client;
        }

        $cars = $client->cars()->orderBy('plate')->get(['plate', 'brand', 'model']);

        return response()->json(['ok' => true, 'vehicles' => $cars]);
    }

    public function store(Request $request)
    {
        $client = $this->client($request);
        if ($client instanceof \Illuminate\Http\JsonResponse) {
            return $client;
        }

        $payload = $request->input('vehicles');
        if (!is_array($payload)) {
            $payload = [[
                'plate' => $request->input('plate'),
                'brand' => $request->input('brand'),
                'model' => $request->input('model'),
            ]];
        }

        if (count($payload) > 500) {
            return response()->json(['ok' => false, 'message' => 'Too many vehicles'], 422);
        }

        $added = 0;
        $skipped = [];
        foreach ($payload as $row) {
            if (!is_array($row)) {
                $skipped[] = ['plate' => '', 'reason' => 'invalid'];
                continue;
            }
            $plate = (string) ($row['plate'] ?? '');
            $result = FleetCars::add($client, $plate, (string) ($row['brand'] ?? ''), (string) ($row['model'] ?? ''), 'api');
            if ($result === 'added') {
                $added++;
            } else {
                $skipped[] = ['plate' => FleetCars::plate($plate), 'reason' => $result];
            }
        }

        return response()->json(['ok' => true, 'added' => $added, 'skipped' => $skipped]);
    }

    public function destroy(Request $request, string $plate)
    {
        $client = $this->client($request);
        if ($client instanceof \Illuminate\Http\JsonResponse) {
            return $client;
        }

        $car = FleetCar::query()
            ->where('corporate_client_id', $client->id)
            ->where('plate', FleetCars::plate($plate))
            ->first();

        if (!$car) {
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        $car->delete();

        return response()->json(['ok' => true]);
    }

    private function client(Request $request): CorporateClient|\Illuminate\Http\JsonResponse
    {
        $key = $request->bearerToken() ?: $request->header('X-Api-Key');
        if (!$key) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $client = CorporateClient::query()->where('api_token', $key)->first();
        if (!$client) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($client->password_must_change) {
            return response()->json(['ok' => false, 'message' => 'Password change required'], 403);
        }

        return $client;
    }
}
