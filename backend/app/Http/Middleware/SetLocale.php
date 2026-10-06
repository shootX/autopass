<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');

        if (in_array($requested, ['en', 'ka'], true)) {
            session(['locale' => $requested]);

            return redirect()->to($request->fullUrlWithoutQuery('lang'));
        }

        $locale = session('locale', 'ka');

        if (! in_array($locale, ['en', 'ka'], true)) {
            $locale = 'ka';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
