<?php

namespace Tests\Unit\TenantSiteSetup;

use App\Models\Page;
use App\Tenant\CurrentTenant;
use App\TenantSiteSetup\SetupItemRegistry;
use App\TenantSiteSetup\SetupItemUrlResolver;
use App\TenantSiteSetup\SetupTargetContextResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class SetupItemUrlResolverTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_home_hero_target_url_includes_relation_query_for_sections_tab(): void
    {
        $tenant = $this->createTenantWithActiveDomain('hero_rel');
        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->firstOrFail();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        $defs = SetupItemRegistry::definitions();
        $def = $defs['pages.home.hero_title'];
        $url = app(SetupItemUrlResolver::class)->urlFor($tenant, $def);

        $this->assertIsString($url);
        $this->assertStringContainsString('relation=0', $url);
    }

    public function test_home_hero_target_context_mismatch_when_relation_query_missing(): void
    {
        $tenant = $this->createTenantWithActiveDomain('hero_mm');
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->firstOrFail();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        $request = Request::create("http://example.test/admin/pages/{$page->id}/edit", 'GET');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);

        $defs = SetupItemRegistry::definitions();
        $def = $defs['pages.home.hero_title'];
        $ctx = app(SetupTargetContextResolver::class)->resolve($tenant, $def, $request);

        $this->assertSame('wrong_page_edit_relation_tab', $ctx['target_context_mismatch']);
        $this->assertFalse($ctx['page_edit_relation_matches']);
        $this->assertSame('0', $ctx['page_edit_relation_tab']);
    }

    public function test_two_visible_programs_target_context_on_index_create_edit(): void
    {
        $tenant = $this->createTenantWithActiveDomain('two_vis');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->firstOrFail();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        $defs = SetupItemRegistry::definitions();
        $def = $defs['programs.two_visible_programs'];

        foreach ([
            'http://example.test/admin/tenant-service-programs',
            'http://example.test/admin/tenant-service-programs/create',
            'http://example.test/admin/tenant-service-programs/99/edit',
        ] as $url) {
            $request = Request::create($url, 'GET');
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(static fn () => $route);

            $ctx = app(SetupTargetContextResolver::class)->resolve($tenant, $def, $request);

            $this->assertTrue($ctx['on_target_route'], 'on_target_route for '.$url);
            $this->assertTrue($ctx['can_complete_here'], 'can_complete_here for '.$url);
        }
    }

    public function test_first_published_program_not_completable_on_index_only(): void
    {
        $tenant = $this->createTenantWithActiveDomain('first_pub');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->firstOrFail();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        $defs = SetupItemRegistry::definitions();
        $def = $defs['programs.first_published_program'];

        $request = Request::create('http://example.test/admin/tenant-service-programs', 'GET');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);

        $ctx = app(SetupTargetContextResolver::class)->resolve($tenant, $def, $request);

        $this->assertTrue($ctx['on_target_route']);
        $this->assertFalse($ctx['can_complete_here']);
    }
}
