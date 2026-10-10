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
        if (!$client) {
            return redirect()->route('partner.login');
        }

        $request->attributes->set('corporate', $client);
        view()->share('corporateClient', $client);

        return $next($request);
    }
}
