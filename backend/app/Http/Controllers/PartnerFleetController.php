<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FleetCarActions;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use Illuminate\Http\Request;

/** Company panel: a car's driver phone, invite resend, block toggle, wash mode and share of the washes. */
class PartnerFleetController extends Controller
{
    use FleetCarActions;

    public function driver(Request $request, FleetCar $car)
    {
        return $this->changeDriver($request, $this->own($request, $car), route('partner.fleet'));
    }

    public function invite(Request $request, FleetCar $car)
    {
        return $this->resendInvite($this->own($request, $car), route('partner.fleet'));
    }

    public function block(Request $request, FleetCar $car)
    {
        return $this->toggleBlock($this->own($request, $car), route('partner.fleet'));
    }

    public function mode(Request $request, FleetCar $car)
    {
        return $this->changeMode($request, $this->own($request, $car), route('partner.fleet'));
    }

    public function washes(Request $request, FleetCar $car)
    {
        return $this->allocateWashes($request, $this->own($request, $car), route('partner.fleet'), 'company');
    }

    private function own(Request $request, FleetCar $car): FleetCar
    {
        /** @var CorporateClient $corporate */
        $corporate = $request->attributes->get('corporate');
        abort_unless((int) $car->corporate_client_id === (int) $corporate->id, 404);

        return $car;
    }
}
