<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sms\SmsInbox;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SmsInboxController extends Controller
{
    public function index(): Response
    {
        $this->guard();

        return response()
            ->view('admin.sms.inbox', ['messages' => SmsInbox::messages()])
            ->header('Cache-Control', 'no-store');
    }

    public function messages(): JsonResponse
    {
        $this->guard();

        return response()
            ->json(['messages' => SmsInbox::messages()])
            ->header('Cache-Control', 'no-store');
    }

    private function guard(): void
    {
        $user = auth()->user();
        abort_unless($user && (int) $user->role === User::ROLE_ADMIN, 403);
        abort_unless(SmsInbox::enabled(), 404);
    }
}
