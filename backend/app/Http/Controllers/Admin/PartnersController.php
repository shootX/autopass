<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PartnersExport;
use App\Http\Controllers\Controller;
use App\Mail\TemporaryPasswordMail;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Services\Security\TemporaryPassword;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PartnersController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new PartnersExport(),
                'partners.xlsx'
            );
        }
        return redirect()->route('admin.corporate');
    }

    public function delete(Request $request, Partner $client)
    {
        $client->delete();
        return redirect()->route('admin.partners')->with('message', __('admin.partner_deleted'));
    }

    public function addPage(Request $request)
    {
        return redirect()->route('admin.corporate.add');
    }

    public function editPage(Request $request, Partner $client)
    {
        return view('admin.partners.edit', compact('client'));
    }

    public function editSave(Request $request, Partner $client)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'description' => 'required|min:5',
            'login' => 'required|string|min:3|unique:partners,login,'.$client->id,
            'email' => 'nullable|email|unique:partners,email,'.$client->id,
        ]);

        if ($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $client->update($validate->validated());

        return redirect()->route('admin.partners')->with('message', __('admin.partner_updated'));
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'description' => 'required|min:5',
            'login' => 'required|string|min:3|unique:partners',
            'email' => 'required|email|unique:partners,email',
        ]);

        if ($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $data = $validate->validated();
        $plain = TemporaryPassword::generate();
        $hours = (int) config('security.temp_password_hours');

        try {
            DB::transaction(function () use ($data, $plain, $hours) {
                $partner = Partner::query()->create([
                    ...$data,
                    'password' => $plain,
                    'password_must_change' => true,
                    'temp_password_expires_at' => now()->addHours($hours),
                    'token_version' => 1,
                ]);

                Mail::to($partner->email)->send(new TemporaryPasswordMail(
                    $partner->name,
                    $partner->login,
                    $plain,
                    $hours
                ));
            });
        } catch (Throwable $e) {
            Log::warning('partner_temp_password_failed', ['exception' => $e::class]);

            return back()->withInput()->withErrors(['email' => __('admin.temp_password_email_failed')]);
        }

        return redirect()->route('admin.partners')->with('message', __('admin.temp_password_sent'));
    }

    public function temporaryPassword(Partner $client)
    {
        if (! filter_var((string) $client->email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => __('admin.temp_password_email_required')]);
        }

        $plain = TemporaryPassword::generate();
        $hours = (int) config('security.temp_password_hours');
        $previous = [
            'password' => $client->password,
            'password_must_change' => $client->password_must_change,
            'temp_password_expires_at' => $client->temp_password_expires_at,
            'token_version' => $client->token_version,
        ];

        try {
            DB::transaction(function () use ($client, $plain, $hours) {
                $client->password = $plain;
                $client->password_must_change = true;
                $client->temp_password_expires_at = now()->addHours($hours);
                $client->token_version = (int) $client->token_version + 1;
                $client->save();

                Mail::to($client->email)->send(new TemporaryPasswordMail(
                    $client->name,
                    $client->login,
                    $plain,
                    $hours
                ));
            });
        } catch (Throwable $e) {
            $client->forceFill($previous)->save();
            Log::warning('partner_temp_password_failed', ['exception' => $e::class]);

            return back()->withErrors(['email' => __('admin.temp_password_email_failed')]);
        }

        return back()->with('message', __('admin.temp_password_sent'));
    }
}
