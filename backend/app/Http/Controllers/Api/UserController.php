<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FleetInvites;
use App\Services\Security\AuthChallengeException;
use App\Services\Security\EmailChallengeService;
use App\Services\Security\SmsDeliveryException;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function me()
    {
        // Opening the app after a company's invite SMS activates the invited fleet cars.
        FleetInvites::activateFor(auth()->user());

        return response()->json(['success' => true, 'user' => auth()->user()]);
    }

    public function edit(Request $request)
    {
        $user = auth()->user();

        // Phone and email are never changed here: phone only via /change_phone (SMS), email only via /email/set + /email/verify.
        // Unchanged values (the SPA posts the whole form) are accepted and ignored.
        if ($request->filled('phone') && Phone::normalize((string) $request->input('phone')) !== Phone::normalize((string) $user->phone)) {
            return response()->json(['success' => false, 'errors' => ['phone' => ['Use the phone change flow']]], 422);
        }
        if ($request->filled('email') && strtolower(trim((string) $request->input('email'))) !== strtolower((string) $user->email)) {
            return response()->json(['success' => false, 'errors' => ['email' => ['Use the email verification flow']]], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'surname' => 'sometimes|required|string|max:255',
            'sex' => 'sometimes|required|in:male,female',
            'date_of_birth' => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $user->update($request->only(['name', 'surname', 'sex', 'date_of_birth']));

        return response()->json(['success' => true, 'message' => 'Успешное обновление данных']);
    }

    public function pushSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wash_appointment' => 'sometimes|required|boolean',
            'renewal_subscription' => 'sometimes|required|boolean',
            'special_promotions' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        if ($request->has('wash_appointment')) {
            $user->enable_push_wash_appointment = $request->input('wash_appointment');
        }

        if ($request->has('renewal_subscription')) {
            $user->enable_push_renewal_subscription = $request->input('renewal_subscription');
        }

        if ($request->has('special_promotions')) {
            $user->enable_push_special_promotions = $request->input('special_promotions');
        }

        $user->update();

        return response()->json(['success' => true, 'message' => 'Настройки push уведомлений успешно обновлены']);
    }

    public function transactions(Request $request)
    {
        return response()->json(['success' => true, 'transactions' => auth()->user()->transactions]);
    }

    public function emailSet(Request $request, EmailChallengeService $emailChallenges)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,'.auth()->id(),
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => 'email not valid or already exists'], 422);
        }

        try {
            $emailChallenges->issue(auth()->user(), (string) $request->input('email'));
        } catch (AuthChallengeException) {
            return response()->json(['success' => false, 'errors' => 'Email verification code already sent. Please check your email or wait before requesting another'], 422);
        } catch (SmsDeliveryException) {
            return response()->json(['success' => false, 'errors' => 'Email could not be sent'], 503);
        }

        return response()->json(['success' => true, 'message' => 'Verification code sent']);
    }

    public function emailVerify(Request $request, EmailChallengeService $emailChallenges)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => 'code validation failed'], 422);
        }

        try {
            $emailChallenges->verify(auth()->user(), (string) $request->input('code'));
        } catch (AuthChallengeException) {
            return response()->json(['success' => false, 'errors' => 'Invalid verification code'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Email verified successfully']);
    }
}
