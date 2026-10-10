<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureApiSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $claim = JWTAuth::parseToken()->getPayload()->get('tv');
        $version = $claim === null ? 0 : (int) $claim;
        if ($version !== (int) $user->token_version) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        if ((int) $user->role === \App\Models\User::ROLE_USER && $user->phone_verified_at === null) {
            return response()->json(['success' => false, 'error' => 'Account is not confirmed'], 403);
        }

        return $next($request);
    }
}
