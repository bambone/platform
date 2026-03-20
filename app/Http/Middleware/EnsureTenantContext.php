<?php

namespace App\Http\Middleware;

use App\Services\CurrentTenantManager;
use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function __construct(
        protected CurrentTenantManager $manager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->manager->getTenant() === null) {
            $host = strtolower(explode(':', $request->getHost())[0]);
            if (! app(TenantResolver::class)->isPlatformHost($host)) {
                return response()->view('errors.domain-not-connected', [], 404);
            }

            abort(404, 'Tenant not found');
        }

        return $next($request);
    }
}
