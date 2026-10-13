<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsurePartnerReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $partner = auth('partners')->user();
        if (! $partner) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $claim = JWTAuth::parseToken()->getPayload()->get('tv');
        $version = $claim === null ? 0 : (int) $claim;
        if ($version !== (int) $partner->token_version) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $allowed = $request->routeIs('partner.api.password', 'partner.api.logout');
        if ($partner->password_must_change && ! $allowed) {
            return response()->json([
                'success' => false,
                'error' => 'Password change required',
                'must_change_password' => true,
            ], 403);
        }

        return $next($request);
    }
}
