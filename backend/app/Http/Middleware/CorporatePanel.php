<?php

namespace App\Http\Middleware;

use App\Models\CorporateClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorporatePanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = CorporateClient::query()->find($request->session()->get('corporate_client_id'));
        $version = $request->session()->get('corporate_session_version');
        if (! $client || $version === null || (int) $version !== (int) $client->session_version) {
            $request->session()->forget(['corporate_client_id', 'corporate_session_version']);

            return redirect()->route('partner.login');
        }

        $allowed = $request->routeIs('partner.password', 'partner.password.save', 'partner.logout');
        if ($client->password_must_change && ! $allowed) {
            return redirect()->route('partner.password');
        }

        $request->attributes->set('corporate', $client);
        view()->share('corporateClient', $client);

        return $next($request);
    }
}
