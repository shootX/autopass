<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $promo = Promo::first();
        return view('admin.promo.index', compact('promo'));
    }

    public function save(Request $request)
    {
        if($request->has('remove'))
        {
            Promo::query()->delete();
            return redirect()->back()->with(['success' => __('admin.promo_removed')]);
        }
        $promo = Promo::query()->firstOrCreate(
            [
                'title' => $request->title,
                'description' => $request->description,
                'url' => $request->url
            ]
        );
        $promo->save();
        return redirect()->back()->with(['success' => __('admin.promo_saved')]);
    }
}
