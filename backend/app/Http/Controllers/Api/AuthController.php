<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureApiSession;
use App\Jobs\SendSms;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Security\ActionGrantService;
use App\Services\Security\AuthChallengeException;
use App\Services\Security\RefreshTokens;
use App\Services\Security\SmsChallengeService;
use App\Services\Sms\IssuedSms;
use App\Services\Sms\SmsStatus;
use App\Services\Sms\SmsUserMessage;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private RefreshTokens $refreshTokens) {}

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
            // Same answer as for a new number, so /register can't be used to list customers.
            return $this->decoySmsJson(hashLikeNewUser: true);
        }

        $createdNow = false;
        if (! $existing) {
            $referralId = null;
            if ($request->filled('code')) {
                $referralId = User::query()->where('ref_code', $request->code)->value('id');
            }

            $existing = User::query()->forceCreate([
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
            $issued = $sms->issueQueued($existing, 'verify');
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        }

        return $this->smsJson($issued, $createdNow, $existing);
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

        [$refresh, $family, $cookie] = $this->refreshTokens->start($user->fresh());
        $token = JWTAuth::claims(['sess' => now()->timestamp, 'sid' => $family])->fromUser($user->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Пароль успешно установлен',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user->fresh(),
        ])->withCookie($cookie);
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

        // Account state is only revealed after the password matched, so it can't be used to list numbers.
        if (! Hash::check((string) $request->password, (string) $user->password)) {
            return response()->json(['success' => false, 'error' => 'Не верный логин либо пароль.'], 401);
        }

        if ((int) $user->role === User::ROLE_USER && $user->phone_verified_at === null) {
            return response()->json(['success' => false, 'error' => 'Account is not confirmed'], 403);
        }

        if ($user->ban) {
            return response()->json(['success' => false, 'error' => 'Account is blocked'], 403);
        }

        if ($user->password_reset_required) {
            return response()->json([
                'success' => false,
                'error' => 'Password reset required',
                'password_reset_required' => true,
            ], 403);
        }

        $family = bin2hex(random_bytes(16));
        if (! $token = JWTAuth::claims(['sess' => now()->timestamp, 'sid' => $family])->attempt($request->only('phone', 'password'))) {
            return response()->json(['success' => false, 'error' => 'Не верный логин либо пароль.'], 401);
        }
        [, , $cookie] = $this->refreshTokens->start($user->fresh(), $family);

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => auth('api')->user(),
        ])->withCookie($cookie);
    }

    /**
     * POST /api/refresh. Preferred: the httpOnly refresh cookie (no Authorization header needed,
     * works after the access token expired); the cookie is rotated on every call. Legacy
     * fallback without the cookie: a still-valid bearer token is rotated as before.
     * See docs/auth-contract.md.
     */
    public function refresh(Request $request)
    {
        $presented = $request->cookie(RefreshTokens::COOKIE);
        if (is_string($presented) && $presented !== '') {
            return $this->refreshFromCookie($presented);
        }

        auth()->shouldUse('api');
        if (! auth('api')->check()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        return app(EnsureApiSession::class)->handle($request, fn () => $this->refreshFromBearer());
    }

    private function refreshFromCookie(string $presented): JsonResponse
    {
        $lock = Cache::lock('refresh-cookie:'.hash('sha256', $presented), 10);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'error' => 'Refresh already in progress'], 409);
        }
        try {
            $consumed = $this->refreshTokens->consume($presented);
            if (! $consumed) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 401)
                    ->withCookie($this->refreshTokens->forget());
            }
            [$user, $family, $sess] = $consumed;
            [, , $cookie] = $this->refreshTokens->start($user, $family, $sess);
            $token = JWTAuth::claims(['sess' => $sess, 'sid' => $family])->fromUser($user);
        } finally {
            $lock->release();
        }

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ])->withCookie($cookie);
    }

    /** Legacy body/bearer rotation (kept for clients that don't send the cookie yet). */
    private function refreshFromBearer(): JsonResponse
    {
        $user = auth('api')->user();
        $old = JWTAuth::getToken();
        if (! $user || ! $old) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        $payload = JWTAuth::setToken($old)->getPayload();

        // The login's first issue time travels with every rotated token, so refreshing
        // can't keep one stolen session alive forever.
        $sess = (int) ($payload->get('sess') ?: $payload->get('iat'));
        if (now()->timestamp - $sess > (int) config('security.session_max_minutes', 43200) * 60) {
            JWTAuth::setToken($old)->invalidate();

            return response()->json(['success' => false, 'error' => 'Session expired'], 401);
        }

        // One rotation per token: a parallel refresh of the same token is turned away, and
        // one that comes after finds the old token blacklisted.
        $lock = Cache::lock('jwt-refresh:'.$payload->get('jti'), 10);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'error' => 'Refresh already in progress'], 409);
        }
        try {
            if (JWTAuth::manager()->getBlacklist()->has($payload)) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
            }
            $family = $payload->get('sid') ?: bin2hex(random_bytes(16));
            $token = JWTAuth::claims(['sess' => $sess, 'sid' => $family])->fromUser($user);
            JWTAuth::setToken($old)->invalidate();
            // Moves legacy clients onto the cookie flow from here on.
            [, , $cookie] = $this->refreshTokens->start($user, $family, $sess);
        } finally {
            $lock->release();
        }

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ])->withCookie($cookie);
    }

    public function logout()
    {
        $token = JWTAuth::getToken();
        if ($token) {
            $this->refreshTokens->revokeFamily(JWTAuth::setToken($token)->getPayload()->get('sid'));
            JWTAuth::invalidate($token);
        }
        auth()->logout();

        return response()->json(['success' => true, 'message' => 'Успешный выход'])
            ->withCookie($this->refreshTokens->forget());
    }

    public function changePhone(Request $request, SmsChallengeService $sms)
    {
        Phone::prepare($request);
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'regex:'.Phone::RULE],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        // A number held by someone else gets the same answer and no SMS, so this can't be used to
        // tell which numbers are registered. The verify step still rejects it.
        if (User::query()->where('phone', $request->phone)->whereKeyNot(auth()->id())->exists()) {
            return $this->decoySmsJson();
        }

        try {
            $issued = $sms->issueQueued(auth()->user(), 'change', ['phone' => $request->phone], $request->phone);
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        }

        return $this->smsJson($issued);
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
            // Scoped to the signed-in user: someone else's code is neither accepted nor burned.
            $row = $sms->verify((string) $request->temp_code, (string) $request->code, 'change', (int) auth()->id());
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
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 422);
        }

        $user = User::query()->where('phone', $request->phone)->first();
        if (! $user) {
            // Unknown number: same answer, no SMS.
            return $this->decoySmsJson();
        }

        try {
            $issued = $sms->issueQueued($user, 'reset');
        } catch (AuthChallengeException $e) {
            return $this->challengeError($e);
        }

        return $this->smsJson($issued);
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
        $user->forceFill(['password_reset_required' => false]);
        $user->phone_verified_at = $user->phone_verified_at ?: now();
        $user->token_version = (int) $user->token_version + 1;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Пароль успешно изменён. Можете войти.']);
    }

    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        // Personal data is erased; the row stays for orders/ledger history. The phone becomes a
        // unique tombstone, so the same number can register and be deleted again.
        $user->forceFill([
            'name' => null,
            'surname' => null,
            'email' => null,
            'pending_email' => null,
            'date_of_birth' => null,
            'sex' => null,
            'password' => Str::password(40),
            'token_version' => (int) $user->token_version + 1,
        ])->save();
        // Written past the phone mutator (it would strip this to digits and let tombstones collide
        // or even look like a real number). The id makes it unique; it never matches a phone rule.
        DB::table('users')->where('id', $user->id)->update(['phone' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(6))]);
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

        UserPushToken::query()->where('push_id', $pushID)->delete();
        $user->pushTokens()->delete();
        $user->pushTokens()->create(['push_id' => $pushID]);

        return response()->json(['success' => true]);
    }

    /**
     * Answer for numbers that must not be revealed (registered / unknown / taken). Mirrors the
     * real path's work: a queued SMS job (a no-op here) and, for /register, the bcrypt a new
     * account costs. The real paths never call the gateway inline either (issueQueued).
     */
    private function decoySmsJson(bool $hashLikeNewUser = false): JsonResponse
    {
        if ($hashLikeNewUser) {
            Hash::make(Str::random(16));
        }
        SendSms::dispatch();
        $simulated = SmsChallengeService::queuedStatus() === SmsStatus::Simulated;

        return response()->json([
            'success' => true,
            'temp_code' => bin2hex(random_bytes(16)),
            'delivery' => $simulated ? SmsStatus::Simulated->value : SmsStatus::Accepted->value,
            'message' => $simulated
                ? 'კოდი შეიქმნა სატესტო რეჟიმში.'
                : 'მოთხოვნა მიღებულია. შეიყვანეთ კოდი, როცა შეტყობინება მოგივათ.',
        ]);
    }

    private function smsJson(IssuedSms $issued, bool $rollbackNewUser = false, ?User $user = null): JsonResponse
    {
        $status = $issued->result->status;
        if ($status === SmsStatus::Failed && $rollbackNewUser && $user) {
            $user->delete();
        }

        if ($status === SmsStatus::Simulated || $status === SmsStatus::Accepted) {
            return response()->json([
                'success' => true,
                'temp_code' => $issued->publicId,
                'delivery' => $status->value,
                'message' => $status === SmsStatus::Simulated
                    ? 'კოდი შეიქმნა სატესტო რეჟიმში.'
                    : 'მოთხოვნა მიღებულია. შეიყვანეთ კოდი, როცა შეტყობინება მოგივათ.',
            ]);
        }

        $payload = [
            'success' => false,
            'delivery' => $status->value,
            'error' => SmsUserMessage::forResult($issued->result),
        ];
        if ($status === SmsStatus::Unknown) {
            $payload['temp_code'] = $issued->publicId;
        }

        return response()->json($payload, $status === SmsStatus::Unknown ? 503 : 422);
    }

    private function challengeError(AuthChallengeException $e): JsonResponse
    {
        $message = $e->reason === 'resend'
            ? 'СМС уже отправлялось ранее. Пожалуйста, подождите перед повторной отправкой.'
            : 'Неверные данные либо код из смс. Повторите попытку позже.';

        return response()->json(['success' => false, 'error' => $message], $e->status);
    }
}
