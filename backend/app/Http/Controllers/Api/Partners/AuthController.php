<?php

namespace App\Http\Controllers\Api\Partners;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'login' => 'required|min:3',
                'password' => 'required',
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wrong login or password'
                ], 401);
            }

            $credentials = $request->only('login', 'password');

            if (!$token = auth('partners')->attempt($credentials)) {
                return response()->json(['success' => false, 'error' => 'Wrong login or password'], 401);
            }

            return response()->json([
                'success' => true,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ], 200);

        } catch (\Exception $ex) {
            return response()->json(['success' => false, 'error' => $ex->getMessage()], 400);
        }
    }
}
