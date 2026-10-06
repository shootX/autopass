<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerificationMail;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function me()
    {
        return response()->json(['success' => true, 'user' => auth()->user()]);
    }

    public function edit(Request $request)
    {
        Phone::prepare($request);
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'surname' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255',
            'phone' => ['sometimes', 'required', 'regex:'.Phone::RULE],
            'sex' => 'sometimes|required|in:male,female',
            'date_of_birth' => 'sometimes|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $user = auth()->user();
        $user->update($request->only(['name', 'surname', 'email', 'phone', 'sex', 'date_of_birth']));
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

        if($request->has('wash_appointment')) {
            $user->enable_push_wash_appointment = $request->input('wash_appointment');
        }

        if($request->has('renewal_subscription')) {
            $user->enable_push_renewal_subscription = $request->input('renewal_subscription');
        }

        if($request->has('special_promotions')) {
            $user->enable_push_special_promotions = $request->input('special_promotions');
        }

        $user->update();

        return response()->json(['success' => true, 'message' => 'Настройки push уведомлений успешно обновлены']);
    }

    public function transactions(Request $request)
    {
        return response()->json(['success' => true, 'transactions' => auth()->user()->transactions]);
    }

    public function emailSet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
        ]);

        if($validator->fails()) {
            return response()->json(['success' => false, 'errors' => 'email not valid or already exists'], 422);
        }

        $user = auth()->user();

        //Check last 10 minutes
        $check = $user->verificationCodes()->where('created_at', '>', now()->subMinutes(10))->first();
        if($check)
        {
            return response()->json(['success' => false, 'errors' => 'Email verification code already sent. Please check your email or wait 10 minutes'], 422);
        }

        $user->email = $request->input('email');
        $user->email_verified_at = null;
        $user->save();

        $code = rand(100000, 999999);

        $user->verificationCodes()->create([
            'code' => $code
        ]);

        Mail::to($user->email)->send(
            new VerificationMail($code)
        );

        return response()->json(['success' => true, 'message' => 'Verification code sent']);
    }

    public function emailVerify(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'code' => 'required|integer|digits:6'
        ]);

        if($validator->fails()) {
            return response()->json(['success' => false, 'errors' => 'code validation failed'], 422);
        }

        $user = auth()->user();
        $code = $request->input('code');

        $verificationCode = $user->verificationCodes()->where('code', $code)->first();

        if (!$verificationCode) {
            return response()->json(['success' => false, 'errors' => 'Invalid verification code'], 422);
        }

        $user->markEmailAsVerified();
        $verificationCode->delete();

        return response()->json(['success' => true, 'message' => 'Email verified successfully']);
    }
}
