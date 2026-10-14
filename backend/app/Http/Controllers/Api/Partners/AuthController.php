<?php

namespace App\Http\Controllers\Api\Partners;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'login' => 'required|min:3',
            'password' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Wrong login or password',
            ], 401);
        }

        $partner = Partner::query()->where('login', $request->input('login'))->first();
        if (! $partner || ! Hash::check((string) $request->input('password'), (string) $partner->password)) {
            return response()->json(['success' => false, 'error' => 'Wrong login or password'], 401);
        }

        if ($partner->password_must_change && $partner->temp_password_expires_at && $partner->temp_password_expires_at->isPast()) {
            return response()->json(['success' => false, 'error' => 'Temporary password expired'], 401);
        }

        $token = auth('partners')->login($partner);

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('partners')->factory()->getTTL() * 60,
            'must_change_password' => (bool) $partner->password_must_change,
        ]);
    }

    public function logout()
    {
        $token = JWTAuth::getToken();
        if ($token) {
            auth('partners')->setToken($token)->invalidate();
        }

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function password(Request $request)
    {
        $partner = auth('partners')->user();
        $validate = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'error' => $validate->errors()], 422);
        }

        if (! $partner->password_must_change) {
            return response()->json(['success' => false, 'error' => 'Password change is not required'], 422);
        }

        $partner->password = $request->input('password');
        $partner->password_must_change = false;
        $partner->temp_password_expires_at = null;
        $partner->token_version = (int) $partner->token_version + 1;
        $partner->save();

        $old = JWTAuth::getToken();
        if ($old) {
            auth('partners')->setToken($old)->invalidate();
        }

        $token = auth('partners')->login($partner->fresh());

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('partners')->factory()->getTTL() * 60,
            'must_change_password' => false,
        ]);
    }
}
