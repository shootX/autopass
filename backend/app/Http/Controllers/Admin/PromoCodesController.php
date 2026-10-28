<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoCodesController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['code' => PromoCode::normalize((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/u', 'unique:promo_codes,code'],
            'kind' => ['required', Rule::in([PromoCode::KIND_DISCOUNT, PromoCode::KIND_POINTS])],
            'value' => ['required', 'integer', 'min:1', $request->input('kind') === PromoCode::KIND_DISCOUNT ? 'max:99' : 'max:100000'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], [], ['code' => __('admin.promo_code'), 'value' => __('admin.promo_value')]);

        PromoCode::query()->create($data + ['active' => true]);

        return redirect()->route('admin.points')->with('message', __('admin.promo_code_added'));
    }

    public function toggle(PromoCode $promoCode)
    {
        $promoCode->update(['active' => ! $promoCode->active]);

        return redirect()->route('admin.points')->with('message', __('admin.promo_code_updated'));
    }

    public function delete(PromoCode $promoCode)
    {
        $promoCode->delete();

        return redirect()->route('admin.points')->with('message', __('admin.promo_code_deleted'));
    }
}
