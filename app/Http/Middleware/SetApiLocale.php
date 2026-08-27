<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('tourism.locales', ['en']);
        $requested = $request->query('locale');
        $locale = is_string($requested) && in_array($requested, $supported, true)
            ? $requested
            : ($request->getPreferredLanguage($supported) ?? config('app.fallback_locale', 'en'));

        app()->setLocale($locale);
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
