<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AppSettings;
use Illuminate\Http\Request;

/** The seller's requisites, VAT, prefix and days to pay on new invoices; an empty field keeps the .env value. */
class InvoiceSettingsController extends Controller
{
    public const SELLER = ['name', 'identification_code', 'address', 'bank_name', 'bank_code', 'bank_account', 'email', 'phone'];

    public function edit()
    {
        return view('admin.corporate.invoice-settings', [
            'config' => AppSettings::invoices(),
            'fields' => self::SELLER,
        ]);
    }

    public function save(Request $request)
    {
        $rules = [
            'prefix' => 'nullable|string|max:8|regex:/^[A-Za-z0-9]+$/',
            'due_days' => 'nullable|integer|min:0|max:90',
            'vat_rate' => 'nullable|integer|min:0|max:100',
            'email' => 'nullable|email|max:120',
        ];
        foreach (self::SELLER as $field) {
            $rules[$field] ??= 'nullable|string|max:255';
        }
        $data = $request->validate($rules);
        AppSettings::store(['invoice' => array_map(fn ($v) => $v === null ? '' : trim((string) $v), $data)]);

        return redirect()->route('admin.corporate.invoices.settings')->with('message', __('admin.inv_settings_saved'));
    }
}
