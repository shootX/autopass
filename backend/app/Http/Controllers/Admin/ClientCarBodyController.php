<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserCar;
use App\Services\CarBody;
use App\Services\CarBodyStatus;
use Illuminate\Http\Request;

/** A client's car whose body type is not confirmed: ask geoapi.ge again, or confirm a type by hand. */
class ClientCarBodyController extends Controller
{
    public function check(User $client, UserCar $car)
    {
        $car = $this->own($client, $car);
        $status = app(CarBodyStatus::class)->recheck($car, 'admin');

        return redirect()->route('admin.clients.cars', $client)->with('message', __('admin.fleet_body_checked_'.$status, ['plate' => $car->plate]));
    }

    public function confirm(Request $request, User $client, UserCar $car)
    {
        $car = $this->own($client, $car);
        $data = $request->validate(['body_type' => ['required', 'string', 'max:32']]);
        if (app(CarBodyStatus::class)->choose($car, $data['body_type'], CarBody::BY_ADMIN, 'admin') !== 'ok') {
            return redirect()->route('admin.clients.cars', $client)->withErrors(['body_type' => __('admin.fleet_reason_body_type_invalid')]);
        }

        return redirect()->route('admin.clients.cars', $client)->with('message', __('admin.fleet_body_checked_confirmed', ['plate' => $car->plate]));
    }

    private function own(User $client, UserCar $car): UserCar
    {
        abort_unless((int) $car->user_id === (int) $client->id, 404);

        return $car;
    }
}
