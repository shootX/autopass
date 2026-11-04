<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public const LANGS = ['ka', 'en', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        // The app sends the language the driver picked in it (X-App-Lang); otherwise the phone's language.
        $app = strtolower((string) $request->header('X-App-Lang', ''));
        $app = $app === 'ge' ? 'ka' : $app;
        $chosen = in_array($app, self::LANGS, true) ? $app : null;
        app()->setLocale($chosen ?? $request->getPreferredLanguage(self::LANGS) ?? 'ka');

        $response = $next($request);

        // Older answers still carry fixed English or Russian texts: an app that names its language gets them in it
        // (QA audit 2026-11-04, QA-10). Without X-App-Lang the answer stays as it was, for older builds.
        if ($chosen !== null && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                $changed = false;
                foreach (['error', 'message', 'msg'] as $key) {
                    if (is_string($data[$key] ?? null) && ($text = self::translate($data[$key], $chosen)) !== null) {
                        $data[$key] = $text;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $response->setData($data);
                }
            }
        }

        return $response;
    }

    /** The fixed text in $lang, or null when it isn't one of the known texts. */
    public static function translate(string $text, string $lang): ?string
    {
        static $known = null;
        $known ??= require lang_path('api_messages.php');

        return $known[$text][$lang] ?? null;
    }
}
