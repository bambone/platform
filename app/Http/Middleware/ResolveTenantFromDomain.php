<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\CurrentTenantManager;
use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromDomain
{
    public function __construct(
        protected TenantResolver $resolver,
        protected CurrentTenantManager $manager
    ) {}

    /**
     * Handle an incoming request.
     * Resolves tenant once per request and stores in context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(explode(':', $request->getHost())[0]);

        if ($this->resolver->isPlatformHost($host)) {
            $this->manager->setTenant(null);

            return $next($request);
        }

        $tenant = $this->resolver->resolve($host);

        app()->instance(Tenant::class, $tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
