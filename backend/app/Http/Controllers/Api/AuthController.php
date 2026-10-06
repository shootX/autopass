<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsTemp;
use App\Models\UserPushToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            Phone::prepare($request);
            $validator = Validator::make($request->all(), [
                'phone' => ['required', 'regex:'.Phone::RULE, 'unique:users,phone'],
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => $validator->errors()], 422);
            }

            $referral_id = null;
            if(isset($request->code))
            {
                $referralUser = User::where('ref_code', $request->code)->first();
                if($referralUser)
                {
                    $referral_id = $referralUser->id;
                }
            }

            $refCode = strtoupper(md5($request->phone));
            $refCode = substr($refCode, 0, 5);

            $user = User::create([
                'name' => null,
                'surname' => null,
                'email' => null,
                'phone' => $request->phone,
                'password' => Hash::make('34rbb45i43jnv8493uh'),
                'date_of_birth' => null,
                'role' => User::ROLE_USER,
                'referral_id' => $referral_id,
                'ref_code' => $refCode
            ]);

            $checkSended = SmsTemp::where('user_id', $user->id)
                ->where('created_at', '>', Carbon::now()->subMinutes(5))
                ->first();

            if ($checkSended) {
                return response()->json([
                    'success' => false,
                    'error' => 'SMS для подтверждения регистрации уже было отправлено на Ваш номер. Пожалуйста, подождите 5 минут перед повторной отправкой.',
                ], 422);
            }

            $sms_code = app()->environment('local') ? 123456 : rand(100000, 999999);

            $created = SmsTemp::create([
                'type' => 'verify',
                'user_id' => $user->id,
                'code' => $sms_code
            ]);

            if (! filled(env('SMSOFFICE_API_KEY')) && app()->environment('local')) {
                Log::info('Registration SMS skipped, provider is not configured.', [
                    'phone' => $request->phone,
                    'code' => $sms_code,
                ]);

                return response()->json([
                    'success' => true,
                    'temp_code' => $created->id,
                    'sms_code' => (string) $sms_code,
                    'message' => 'SMS provider is not configured. Use sms_code to verify.',
                ], 200);
            }

            $response = Http::get('https://smsoffice.ge/api/v2/send/', [
                'key' => env('SMSOFFICE_API_KEY'),
                'destination' => Phone::forSms($request->phone),
                'sender' => env('SMSOFFICE_SENDER'),
                'content' => 'Registration code: ' . $sms_code,
            ]);

            $responseData = $response->json();
            $status = is_array($responseData) && ($responseData['Success'] ?? false);

            if ($status) {
                return response()->json([
                    'success' => true,
                    'temp_code' => $created->id,
                    'message' => 'SMS для подтверждения регистрации отправлено на Ваш номер.',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'error' => 'SMS service error: ' . (is_array($responseData) ? ($responseData['Message'] ?? 'unknown') : 'invalid response'),
            ], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage() . ' ' . $e->getLine()], 400);
        }
    }

    public function verify(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'temp_code' => 'required|exists:sms_temps,id',
                'code' => 'required|digits:6',
            ]);

            if ($validate->fails()) {
                return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
            }

            $code = SmsTemp::where('id', $request->temp_code)->first();
            if ($code->code != $request->code) {
                return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
            }

            $user = User::where('id', $code->user_id)->first();
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'user' => $user,
                'message' => 'Регистрация успешна',
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function set_password(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'temp_code' => 'required|exists:sms_temps,id',
                'password' => 'required|string|min:6',
            ]);

            if ($validate->fails()) {
                return response()->json(['success' => false, 'error' => 'Пароль должен быть не менее 6 символов'], 422);
            }

            $code = SmsTemp::where('id', $request->temp_code)->first();

            $user = $code->user;

            $user->password = $request->password;
            $user->save();

            return response()->json(['success' => true, 'message' => 'Пароль успешно установлен'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function login(Request $request)
    {
        try {

            Phone::matchStored($request);
            $request->validate(
                [
                    'phone' => 'required|string|exists:users,phone',
                    'password' => 'required|string|min:6',
                ],
                [
                    'phone.exists' => 'Пользователь с таким номером не найден',
                    'password.min' => 'Пароль должен быть не менее 6 символов'
                ]
            );

            $credentials = $request->only('phone', 'password');

            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['success' => false, 'error' => 'Не верный логин либо пароль.'], 401);
            }

            return response()->json([
                'success' => true,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => auth()->user()
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function logout()
    {
        auth()->logout();
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['success' => true, 'message' => 'Успешный выход']);
    }

    public function changePhone(Request $request)
    {
        Phone::prepare($request);
        $validate = Validator::make($request->all(), [
            'phone' => ['required', 'regex:'.Phone::RULE, 'unique:users,phone,' . auth()->user()->id],
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'error' => $validate->errors()], 422);
        }

        $user = auth()->user();

        $checkSended = SmsTemp::where('user_id', $user->id)
            ->where('created_at', '>', Carbon::now()->subMinutes(5))
            ->first();

        if ($checkSended) {
            return response()->json([
                'success' => false,
                'error' => 'СМС уже отправлялось ранее. Пожалуйста, подождите 5 минут перед повторной отправкой.',
            ], 422);
        }

        //change

        $sms_code = rand(100000, 999999);

        $created = SmsTemp::create([
            'type' => 'change',
            'user_id' => $user->id,
            'code' => $sms_code
        ]);

        Http::get('https://smsoffice.ge/api/v2/send/', [
            'key' => env('SMSOFFICE_API_KEY'),
            'destination' => Phone::forSms($request->phone),
            'sender' => env('SMSOFFICE_SENDER'),
            'content' => 'Phone verify code: ' . $sms_code,
        ]);

        return response()->json([
            'success' => true,
            'temp_code' => $created->id,
            'message' => 'SMS для подтверждения номера отправлено на Ваш новый номер.',
        ], 200);
    }

    public function changePhoneVerify(Request $request)
    {
        Phone::prepare($request);
        $validate = Validator::make($request->all(), [
            'temp_code' => 'required|exists:sms_temps,id',
            'code' => 'required|digits:6',
            'phone' => ['required', 'regex:'.Phone::RULE, 'unique:users,phone,' . auth()->user()->id],
        ]);

        if( $validate->fails()) {
            return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
        }

        $checkCode = SmsTemp::where('id', $request->temp_code)
            ->where('type', 'change')
            ->where('code', $request->code)
            ->first();

        if($checkCode){

            $user = auth()->user();
            $user->phone = $request->phone;
            $user->update();

            return response()->json(['success' => true, 'message' => 'Номер телефона успешно изменён.'], 200);
        } else {
            return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
        }
    }

    public function changePassword(Request $request)
    {
        Phone::matchStored($request);
        $validate = Validator::make($request->all(), [
            'phone' => 'required|string|exists:users,phone',
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'error' => $validate->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        $smsCode = rand(100000, 999999);

        $created = SmsTemp::create([
            'type' => 'reset',
            'user_id' => $user->id,
            'code' => $smsCode
        ]);

        Http::get('https://smsoffice.ge/api/v2/send/', [
            'key' => env('SMSOFFICE_API_KEY'),
            'destination' => Phone::forSms($request->phone),
            'sender' => env('SMSOFFICE_SENDER'),
            'content' => 'Change password code: ' . $smsCode,
        ]);

        return response()->json([
            'success' => true,
            'temp_code' => $created->id,
            'message' => 'SMS для восстановления пароля отправлено на Ваш номер.',
        ], 200);
    }

    public function changePasswordVerify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_code' => 'required|exists:sms_temps,id',
            'code' => 'required|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        $code = SmsTemp::where('id', $request->temp_code)
            ->where('type', 'reset')
            ->where('code', $request->code)
            ->orderByDesc('id')
            ->first();

        if(!$code){
            return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
        }

        $newCode = rand(100000, 999999);

        SmsTemp::create([
            'type' => 'reset',
            'user_id' => $code->user_id,
            'code' => $newCode
        ]);

        return response()->json(['success' => true, 'verify_code' => $newCode, 'message' => 'Код подтверждения верен. Теперь установите новый пароль.'], 200);

    }

    public function changePasswordVerifySubmit(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'verify_code' => 'required|digits:6',
            'password' => 'required|string|min:6'
        ]);

        if ($validate->fails()) {
            return response()->json(['success' => false, 'error' => $validate->errors()], 422);
        }

        $check = SmsTemp::where('type', 'reset')
            ->where('code', $request->verify_code)
            ->orderByDesc('id')
            ->first();

        if(!$check){
            return response()->json(['success' => false, 'error' => 'Неверный код подтверждения.'], 422);
        }

        $user = User::find($check->user_id);

        $user->password = $request->password;
        $user->update();

        return response()->json(['success' => true, 'message' => 'Пароль успешно изменён. Можете войти.'], 200);
    }

    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        $user->phone = $user->phone . '-del';
        $user->update();
        auth()->logout();
        return response()->json(['success' => true, 'message' => 'Аккаунт успешно удалён.']);
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string|max:32',
        ]);

        return response()->json([
            'token' => (string) Str::uuid(),
        ]);
    }

    public function setDevicePushId(Request $request)
    {
        $data = $request->json()->all();
        $pushID = $data['push_id'];

        $user = $request->user();

        UserPushToken::query()->where('push_id', $pushID)->delete();

        $user->pushTokens()->delete();
        $user->pushTokens()->create(['push_id' => $pushID]);

        return response()->json([
            'success' => true
        ], 200);
    }
}
