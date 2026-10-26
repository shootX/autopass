<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FleetCar;
use App\Services\CorporateWashes;
use App\Services\FleetInvites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Driver phone, invite resend, block toggle, wash mode and share, shared by the admin and the company panel. */
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

    protected function changeMode(Request $request, FleetCar $car, string $back): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', 'string', Rule::in(FleetCar::MODES)]]);
        CorporateWashes::setMode($car, $data['mode']);

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
}
