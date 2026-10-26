<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FleetCarActions;
use App\Http\Controllers\Controller;
use App\Models\CorporateClient;
use App\Models\FleetCar;
use Illuminate\Http\Request;

class CorporateCarsController extends Controller
{
    use FleetCarActions;

    public function driver(Request $request, CorporateClient $corporate, FleetCar $car)
    {
        return $this->changeDriver($request, $this->own($corporate, $car), route('admin.corporate.show', $corporate));
    }

    public function invite(CorporateClient $corporate, FleetCar $car)
    {
        return $this->resendInvite($this->own($corporate, $car), route('admin.corporate.show', $corporate));
    }

    public function block(CorporateClient $corporate, FleetCar $car)
    {
        return $this->toggleBlock($this->own($corporate, $car), route('admin.corporate.show', $corporate));
    }

    public function mode(Request $request, CorporateClient $corporate, FleetCar $car)
    {
        return $this->changeMode($request, $this->own($corporate, $car), route('admin.corporate.show', $corporate));
    }

    public function washes(Request $request, CorporateClient $corporate, FleetCar $car)
    {
        return $this->allocateWashes($request, $this->own($corporate, $car), route('admin.corporate.show', $corporate), 'admin', (int) auth()->id());
    }

    private function own(CorporateClient $corporate, FleetCar $car): FleetCar
    {
        abort_unless((int) $car->corporate_client_id === (int) $corporate->id, 404);

        return $car;
    }
}
