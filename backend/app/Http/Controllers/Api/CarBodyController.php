<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CarBody;
use App\Services\CarBodyStatus;
use App\Services\CarCatalog;
use Illuminate\Http\Request;

/**
 * A car's body type in the app: the types a model has in a year (asked while adding a car), and for a saved car
 * that waits for one, the driver's pick from geoapi.ge's types or a retry when geoapi.ge could not tell.
 */
class CarBodyController extends Controller
{
    /**
     * GET /cars/body-types. status confirmed: one type, set on its own, no field. choose: the user picks one of
     * body_types and sends it as car_type (body_incomplete: geoapi.ge left some variants unnamed). unknown:
     * geoapi.ge can't tell (unavailable: it did not answer), so the app says so and offers a retry.
     */
    public function types(Request $request)
    {
        $data = $request->validate([
            'source' => ['required', 'in:'.CarCatalog::LOCAL.','.CarCatalog::REMOTE],
            'brand_id' => ['nullable', 'integer', 'min:1', 'required_if:source,'.CarCatalog::REMOTE],
            'model_id' => ['required', 'integer', 'min:1'],
            'year' => ['nullable', 'integer', 'between:'.CarCatalog::MIN_YEAR.','.((int) now()->year + 1)],
        ]);
        $options = app(CarBody::class)->options($data['source'], (int) ($data['brand_id'] ?? 0), (int) $data['model_id'], $data['year'] ?? null);

        return response()->json([
            'success' => true,
            'status' => $options['status'],
            'body_types' => CarBody::rows($options['types']),
            'choose' => $options['status'] === CarBody::CHOOSE,
            'car_type' => $options['status'] === CarBody::CONFIRMED ? $options['types'][0] : null,
            'incomplete' => $options['incomplete'],
            'unavailable' => $options['unavailable'],
        ]);
    }

    /** POST /mycars/{carid}/body {car_type}: the driver picks one of the types geoapi.ge named for the car. */
    public function choose(Request $request, int $carid)
    {
        $data = $request->validate(['car_type' => ['required', 'string', 'max:32']]);
        $car = $request->user()->cars()->find($carid);
        if (! $car) {
            return response()->json(['success' => false, 'error' => __('cars.car_not_found')], 404);
        }

        $result = app(CarBodyStatus::class)->choose($car, $data['car_type'], CarBody::BY_DRIVER, 'driver');
        if ($result !== 'ok') {
            $code = $result === 'confirmed' ? 'body_confirmed' : $result;

            return response()->json(['success' => false, 'error' => __('cars.'.$code), 'error_code' => $code,
                'body_types' => CarBody::rows($car->body_options ?? [])], 422);
        }

        return response()->json(['success' => true, 'car' => $car->fresh()->appPayload()]);
    }

    /** POST /mycars/{carid}/body/check: asks geoapi.ge again about a car whose body type it could not tell. */
    public function check(Request $request, int $carid)
    {
        $car = $request->user()->cars()->find($carid);
        if (! $car) {
            return response()->json(['success' => false, 'error' => __('cars.car_not_found')], 404);
        }

        $status = app(CarBodyStatus::class)->recheck($car, 'driver');
        $car = $car->fresh();

        return response()->json([
            'success' => true,
            'car' => $car->appPayload(),
            // Still unknown: geoapi.ge did not answer (try later) or has no body type for this car (support).
            'message' => $status === CarBody::UNKNOWN
                ? __($car->body_checked_at === null ? 'cars.body_unavailable' : 'cars.body_unknown')
                : null,
        ]);
    }
}
