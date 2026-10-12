<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kept for route compatibility. CORS is handled by Laravel's global HandleCors
 * using config/cors.php (origins from CORS_ALLOWED_ORIGINS). This middleware
 * used to force "Access-Control-Allow-Origin: *" and no longer does.
 */
class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
