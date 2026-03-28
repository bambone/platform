<?php

namespace Tests\Feature;

use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TenantThemeViewResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function getWithHost(string $host, string $path = '/'): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('GET', 'http://'.$host.$path);
    }

    protected function createTenantSite(string $subdomain, array $tenantAttrs = []): Tenant
    {
        $tenant = Tenant::query()->create(array_merge([
            'name' => 'Theme T',
            'slug' => $subdomain,
            'status' => 'active',
        ], $tenantAttrs));

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => $subdomain.'.apex.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);

        Cache::flush();

        return $tenant->fresh();
    }

    public function test_default_theme_home_renders_engine_content(): void
    {
        $this->createTenantSite('defaulthome', ['theme_key' => 'default']);

        $this->getWithHost('defaulthome.apex.test', '/')
            ->assertOk()
            ->assertSee('Наш автопарк', false)
            ->assertDontSee('data-tenant-theme="moto"', false);
    }

    public function test_omitted_theme_key_uses_database_default(): void
    {
        $this->createTenantSite('omittedtheme');

        $this->getWithHost('omittedtheme.apex.test', '/')
            ->assertOk()
            ->assertSee('Наш автопарк', false);

        $this->assertSame('default', Tenant::query()->where('slug', 'omittedtheme')->value('theme_key'));
    }

    public function test_moto_theme_home_includes_theme_marker(): void
    {
        $this->createTenantSite('motohome', ['theme_key' => 'moto']);

        $this->getWithHost('motohome.apex.test', '/')
            ->assertOk()
            ->assertSee('data-tenant-theme="moto"', false)
            ->assertSee('Наш автопарк', false);
    }

    public function test_moto_theme_dynamic_page_falls_back_without_moto_marker(): void
    {
        $tenant = $this->createTenantSite('motopage', ['theme_key' => 'moto']);

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Theme test page',
            'slug' => 'theme-test-page',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->getWithHost('motopage.apex.test', '/theme-test-page')
            ->assertOk()
            ->assertSee('Theme test page', false)
            ->assertDontSee('data-tenant-theme="moto"', false);
    }

    public function test_moto_theme_motorcycle_show_falls_back_to_engine_template(): void
    {
        $tenant = $this->createTenantSite('motobike', ['theme_key' => 'moto']);

        Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Theme Bike',
            'slug' => 'theme-bike',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);

        $this->getWithHost('motobike.apex.test', '/moto/theme-bike')
            ->assertOk()
            ->assertSee('Theme Bike', false)
            ->assertDontSee('data-tenant-theme="moto"', false);
    }

    public function test_unknown_theme_key_safe_string_falls_back_without_crash(): void
    {
        $this->createTenantSite('unknowntheme', ['theme_key' => 'futurepreset']);

        $this->getWithHost('unknowntheme.apex.test', '/')
            ->assertOk()
            ->assertSee('Наш автопарк', false)
            ->assertDontSee('data-tenant-theme="moto"', false);
    }

    public function test_home_route_name_and_path_unchanged(): void
    {
        $this->createTenantSite('routecheck', ['theme_key' => 'moto']);

        $this->assertSame('/', route('home', [], false));
    }
}
