<?php

namespace App\Http\Middleware;

use App\Models\Motorcycle;
use App\Services\Seo\JsonLdGenerator;
use App\Services\Seo\TenantSeoRequestModelResolver;
use App\Services\Seo\TenantSeoResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantPublicSeo
{
    public function __construct(
        private TenantSeoRequestModelResolver $models,
        private TenantSeoResolver $resolver,
        private JsonLdGenerator $jsonLd,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $route = $request->route();
        if ($route === null) {
            return $next($request);
        }

        $name = $route->getName();
        if (! is_string($name) || $name === '') {
            return $next($request);
        }

        if (in_array($name, ['robots', 'sitemap', 'llms', 'theme.platform.asset', 'tenant.public.storage', 'tenant.public.media'], true)) {
            return $next($request);
        }

        $tenant = tenant();
        if ($tenant === null) {
            return $next($request);
        }

        $model = $this->models->resolve($request, $name);

        $context = [];
        if ($name === 'motorcycles.index') {
            $bikes = Motorcycle::query()
                ->where('show_in_catalog', true)
                ->where('status', 'available')
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->orderBy('sort_order')
                ->get();
            $context['item_list_entries'] = $this->jsonLd->catalogItemEntries($tenant, $bikes);
        }

        $resolved = $this->resolver->resolve($request, $tenant, $name, $model, $context);

        View::share('resolvedSeo', $resolved);

        return $next($request);
    }
}
