<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FleetCar;
use App\Services\CarBody;
use App\Services\CarBodyStatus;
use App\Services\CorporateDiscounts;
use App\Services\CorporateWashes;
use App\Services\FleetInvites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Driver phone, invite resend, block toggle, wash mode and share, body type retry, shared by the admin and the company panel. */
trait FleetCarActions
{
    protected function changeDriver(Request $request, FleetCar $car, string $back): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $car->loadMissing('client');
        $result = FleetInvites::setDriver($car, $data['phone']);
        if ($result === 'phone_invalid') {
            return redirect($back)->withErrors(['fleet' => __('admin.fleet_phone_invalid')]);
        }

        return redirect($back)->with('message', __('admin.fleet_driver_saved'));
    }

    protected function resendInvite(FleetCar $car, string $back): RedirectResponse
    {
        $car->loadMissing('client');
        $result = FleetInvites::invite($car);
        if ($result !== 'sent') {
            return redirect($back)->withErrors(['fleet' => __('admin.fleet_invite_'.$result)]);
        }

        return redirect($back)->with('message', __('admin.fleet_invite_sent'));
    }

    protected function toggleBlock(FleetCar $car, string $back): RedirectResponse
    {
        $block = $car->status !== FleetCar::STATUS_BLOCKED;
        FleetInvites::setBlocked($car, $block);

        return redirect($back)->with('message', __($block ? 'admin.fleet_car_blocked' : 'admin.fleet_car_unblocked'));
    }

    /** Discount mode may name the company's discount package for the car. */
    protected function changeMode(Request $request, FleetCar $car, string $back): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'string', Rule::in(FleetCar::MODES)],
            'discount_package_id' => ['nullable', 'integer'],
        ]);
        $package = null;
        if ($data['mode'] === FleetCar::MODE_DISCOUNT && ! empty($data['discount_package_id'])) {
            $package = CorporateDiscounts::find($car->client, (int) $data['discount_package_id']);
            if (! $package) {
                return redirect($back)->withErrors(['fleet' => __('admin.cd_package_unknown')]);
            }
        }
        CorporateWashes::setMode($car, $data['mode'], $package);

        return redirect($back)->with('message', __('admin.cw_mode_saved'));
    }

    /** @param  'admin'|'company'  $actor */
    protected function allocateWashes(Request $request, FleetCar $car, string $back, string $actor, ?int $actorId = null): RedirectResponse
    {
        $data = $request->validate(['washes' => ['required', 'integer', 'min:0', 'max:100000']]);
        $result = CorporateWashes::allocate($car, (int) $data['washes'], $actor, $actorId);
        if ($result !== 'ok') {
            return redirect($back)->withErrors(['fleet' => __('admin.cw_error_'.$result)]);
        }

        return redirect($back)->with('message', __('admin.cw_car_washes_saved', ['plate' => $car->plate, 'count' => (int) $data['washes']]));
    }

    /** Asks geoapi.ge again about a car whose body type it could not tell. */
    protected function recheckBody(FleetCar $car, string $back, string $actor): RedirectResponse
    {
        $status = app(CarBodyStatus::class)->recheck($car, $actor);

        return redirect($back)->with('message', __('admin.fleet_body_checked_'.$status, ['plate' => $car->plate]));
    }

    /** Admin only: confirms a body type for a car geoapi.ge can't tell (or names wrongly). */
    protected function confirmBody(Request $request, FleetCar $car, string $back): RedirectResponse
    {
        $data = $request->validate(['body_type' => ['required', 'string', 'max:32']]);
        $result = app(CarBodyStatus::class)->choose($car, $data['body_type'], CarBody::BY_ADMIN, 'admin');
        if ($result !== 'ok') {
            return redirect($back)->withErrors(['fleet' => __('admin.fleet_reason_body_type_invalid')]);
        }

        return redirect($back)->with('message', __('admin.fleet_body_checked_confirmed', ['plate' => $car->plate]));
    }
}
