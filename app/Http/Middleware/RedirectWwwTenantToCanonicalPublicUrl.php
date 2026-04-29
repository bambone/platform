<?php

namespace App\Http\Middleware;

use App\Services\Seo\TenantCanonicalPublicBaseUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Если в браузере открыто {@code www.apex.example.com}, а в настройках тенанта {@code general.domain}
 * задаёт apex {@code https://apex.example.com} — выполняется 301 на канонический base + сохранённый path/query.
 * Не заменяет edge/CDN (http→https): обычно это nginx/Cloudflare; здесь только www→non-www на совпадении с каноном.
 */
final class RedirectWwwTenantToCanonicalPublicUrl
{
    public function __construct(
        private readonly TenantCanonicalPublicBaseUrl $canonicalBase,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()) {
            return $next($request);
        }
        if (! config('tenancy.redirect_www_to_canonical_apex', true)) {
            return $next($request);
        }
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }
        $tenant = tenant();
        if ($tenant === null) {
            return $next($request);
        }

        $base = $this->canonicalBase->resolve($tenant);
        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
            return $next($request);
        }

        $canonicalHost = parse_url($base, PHP_URL_HOST);
        if (! is_string($canonicalHost) || $canonicalHost === '') {
            return $next($request);
        }

        $reqHost = strtolower((string) $request->getHost());
        if (! str_starts_with($reqHost, 'www.')) {
            return $next($request);
        }

        $apexFromRequest = substr($reqHost, 4);
        if ($apexFromRequest !== strtolower($canonicalHost)) {
            return $next($request);
        }

        $target = rtrim($base, '/').$request->getRequestUri();

        return redirect()->away($target, Response::HTTP_MOVED_PERMANENTLY);
    }
}
