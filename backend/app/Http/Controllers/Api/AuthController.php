<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\ActionGrantService;
use App\Services\Security\AuthChallengeException;
use App\Services\Security\SmsChallengeService;
use App\Services\Security\SmsDeliveryException;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request, SmsChallengeService $sms)
    {
        Phone::prepare($request);
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:'.Phone::RULE],
        ], [
            'phone.regex' => 'Неверный формат',
            'phone.required' => 'Неверный формат',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        $existing = User::query()->where('phone', $request->phone)->first();
        if ($existing && $existing->phone_verified_at) {
            return response()->json([
                'success' => false,
                'error' => ['phone' => ['Номер уже существует']],
            ], 422);
        }

        $createdNow = false;
        if (! $existing) {
            $referralId = null;
            if ($request->filled('code')) {
                $referralId = User::query()->where('ref_code', $request->code)->value('id');
            }

            $existing = User::query()->create([
                'name' => null,
                'surname' => null,
                'email' => null,
                'phone' => $request->phone,
                'password' => Str::password(40),
                'date_of_birth' => null,
                'role' => User::ROLE_USER,
                'referral_id' => $referralId,
                'ref_code' => substr(strtoupper(md5($request->phone.Str::random(8))), 0, 5),
            ]);
            $createdNow = true;
        }

        try {
            $publicId = $sms->issue($existing, 'verify');
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        } catch (SmsDeliveryException $e) {
            if ($createdNow) {
                $existing->delete();
            }
            Log::warning('registration_sms_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'SMS could not be sent'], 503);
        }

        return response()->json([
            'success' => true,
            'temp_code' => $publicId,
            'message' => 'SMS для подтверждения регистрации отправлено на Ваш номер.',
        ]);
    }

    public function verify(Request $request, SmsChallengeService $sms)
    {
        $validator = Validator::make($request->all(), [
            'temp_code' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
        }

        try {
            $setup = $sms->verifyAndGrant((string) $request->temp_code, (string) $request->code, 'verify', 'set_password');
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        }

        return response()->json([
            'success' => true,
            'setup_token' => $setup,
            'message' => 'Номер подтверждён. Установите пароль.',
        ]);
    }

    public function set_password(Request $request, ActionGrantService $grants)
    {
        $validator = Validator::make($request->all(), [
            'setup_token' => 'required|string|min:20',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Пароль должен быть не менее 6 символов'], 422);
        }

        $user = $grants->consume((string) $request->setup_token, 'set_password');
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Срок подтверждения истёк. Повторите регистрацию.'], 422);
        }

        $user->password = $request->password;
        $user->phone_verified_at = now();
        $user->token_version = (int) $user->token_version + 1;
        $user->save();

        $token = JWTAuth::fromUser($user->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Пароль успешно установлен',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user->fresh(),
        ]);
    }

    public function login(Request $request)
    {
        Phone::matchStored($request);
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string|min:6',
        ], [
            'password.min' => 'Пароль должен быть не менее 6 символов',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        $user = User::query()->where('phone', $request->phone)->first();
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Не верный логин либо пароль.'], 401);
        }

        if ((int) $user->role === User::ROLE_USER && $user->phone_verified_at === null) {
            return response()->json(['success' => false, 'error' => 'Account is not confirmed'], 403);
        }

        if (! $token = JWTAuth::attempt($request->only('phone', 'password'))) {
            return response()->json(['success' => false, 'error' => 'Не верный логин либо пароль.'], 401);
        }

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => auth('api')->user(),
        ]);
    }

    public function logout()
    {
        $token = JWTAuth::getToken();
        if ($token) {
            JWTAuth::invalidate($token);
        }
        auth()->logout();

        return response()->json(['success' => true, 'message' => 'Успешный выход']);
    }

    public function changePhone(Request $request, SmsChallengeService $sms)
    {
        Phone::prepare($request);
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:'.Phone::RULE, 'unique:users,phone,'.auth()->id()],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        try {
            $publicId = $sms->issue(auth()->user(), 'change', ['phone' => $request->phone], null, true, $request->phone);
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        } catch (SmsDeliveryException $e) {
            Log::warning('change_phone_sms_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'SMS could not be sent'], 503);
        }

        return response()->json([
            'success' => true,
            'temp_code' => $publicId,
            'message' => 'SMS для подтверждения номера отправлено на Ваш новый номер.',
        ]);
    }

    public function changePhoneVerify(Request $request, SmsChallengeService $sms)
    {
        Phone::prepare($request);
        $validator = Validator::make($request->all(), [
            'temp_code' => 'required|string',
            'code' => 'required|digits:6',
            'phone' => ['required', 'regex:'.Phone::RULE, 'unique:users,phone,'.auth()->id()],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
        }

        try {
            $row = $sms->verify((string) $request->temp_code, (string) $request->code, 'change');
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        }

        if ((int) $row->user_id !== (int) auth()->id()) {
            return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
        }

        $expected = $row->context['phone'] ?? null;
        if (! is_string($expected) || $expected !== $request->phone) {
            return response()->json(['success' => false, 'error' => 'Неверные данные либо код из смс. Повторите попытку позже.'], 422);
        }

        $user = auth()->user();
        $user->phone = $request->phone;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Номер телефона успешно изменён.']);
    }

    public function changePassword(Request $request, SmsChallengeService $sms)
    {
        Phone::matchStored($request);
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|exists:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        $user = User::query()->where('phone', $request->phone)->first();

        try {
            $publicId = $sms->issue($user, 'reset');
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        } catch (SmsDeliveryException $e) {
            Log::warning('reset_sms_failed', ['exception' => $e::class]);

            return response()->json(['success' => false, 'error' => 'SMS could not be sent'], 503);
        }

        return response()->json([
            'success' => true,
            'temp_code' => $publicId,
            'message' => 'SMS для восстановления пароля отправлено на Ваш номер.',
        ]);
    }

    public function changePasswordVerify(Request $request, SmsChallengeService $sms)
    {
        $validator = Validator::make($request->all(), [
            'temp_code' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        try {
            $setup = $sms->verifyAndGrant((string) $request->temp_code, (string) $request->code, 'reset', 'reset_password');
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        }

        return response()->json([
            'success' => true,
            'setup_token' => $setup,
            'message' => 'Код подтверждения верен. Теперь установите новый пароль.',
        ]);
    }

    public function changePasswordVerifySubmit(Request $request, ActionGrantService $grants)
    {
        $validator = Validator::make($request->all(), [
            'setup_token' => 'required|string|min:20',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        $user = $grants->consume((string) $request->setup_token, 'reset_password');
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Неверный код подтверждения.'], 422);
        }

        $user->password = $request->password;
        $user->phone_verified_at = $user->phone_verified_at ?: now();
        $user->token_version = (int) $user->token_version + 1;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Пароль успешно изменён. Можете войти.']);
    }

    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        $user->phone = $user->phone.'-del';
        $user->token_version = (int) $user->token_version + 1;
        $user->save();
        $token = JWTAuth::getToken();
        if ($token) {
            JWTAuth::invalidate($token);
        }
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
        $pushID = $data['push_id'] ?? null;
        if (! is_string($pushID) || $pushID === '') {
            return response()->json(['success' => false, 'error' => 'Invalid push id'], 422);
        }

        $user = $request->user();

        \App\Models\UserPushToken::query()->where('push_id', $pushID)->delete();
        $user->pushTokens()->delete();
        $user->pushTokens()->create(['push_id' => $pushID]);

        return response()->json(['success' => true]);
    }

    private function challengeError(AuthChallengeException $e): JsonResponse
    {
        $message = $e->reason === 'resend'
            ? 'СМС уже отправлялось ранее. Пожалуйста, подождите перед повторной отправкой.'
            : 'Неверные данные либо код из смс. Повторите попытку позже.';

        return response()->json(['success' => false, 'error' => $message], $e->status);
    }
}
