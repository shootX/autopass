<?php

namespace App\Http\Middleware;

use App\Support\AdminAudit;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every state-changing admin request (and every SMS inbox view) leaves an audit row:
 * who, which route, which record, when. Field values are not stored here.
 */
class AuditAdminRequests
{
    private const VIEW_AUDITED = ['admin.sms_inbox', 'admin.sms_inbox.messages'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $route = $request->route();
        $name = $route?->getName() ?? $route?->uri() ?? $request->path();
        $writes = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        if (! auth()->check() || (! $writes && ! in_array($name, self::VIEW_AUDITED, true)) || $response->getStatusCode() >= 400) {
            return $response;
        }
        // A redirect back carrying validation errors changed nothing.
        if ($response->isRedirection() && $request->hasSession() && $request->session()->has('errors')) {
            return $response;
        }

        [$type, $id] = ['route', 0];
        foreach ((array) $route?->parameters() as $key => $value) {
            if ($value instanceof Model) {
                [$type, $id] = [class_basename($value), (int) $value->getKey()];
                break;
            }
            if (is_numeric($value)) {
                [$type, $id] = [(string) $key, (int) $value];
                break;
            }
        }

        try {
            AdminAudit::record(mb_substr($type, 0, 32), $id, mb_substr((string) $name, 0, 96));
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }
}
