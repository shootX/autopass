<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Services\Points\PointsPricing;
use App\Support\AppSettings;
use Illuminate\Http\Request;

class PointsSettingsController extends Controller
{
    public function index()
    {
        return view('admin.points.index', [
            'settings' => AppSettings::all(),
            'card' => PointsPricing::card(),
            'codes' => PromoCode::query()->latest('id')->paginate(20),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'point_price' => 'required|numeric|min:0.01|max:1000',
            'point_discount' => 'required|integer|min:0|max:99',
            'points_min' => 'required|integer|min:1|max:100000',
            'points_max' => 'required|integer|gte:points_min|max:100000',
            'referral_percent' => 'required|integer|min:0|max:100',
        ]);
        AppSettings::store([
            'point_price' => round((float) $data['point_price'], 2),
            'point_discount' => (int) $data['point_discount'],
            'points_min' => (int) $data['points_min'],
            'points_max' => (int) $data['points_max'],
            'referral_percent' => (int) $data['referral_percent'],
        ]);

        return redirect()->route('admin.points')->with('message', __('admin.points_saved'));
    }
}
