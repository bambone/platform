<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\Tenancy\TenantViewResolver;
use Tests\TestCase;

class TenantViewResolverTest extends TestCase
{
    public function test_resolves_moto_home_before_default_when_theme_is_moto(): void
    {
        $tenant = new Tenant(['theme_key' => 'moto']);
        $resolved = app(TenantViewResolver::class)->resolve('pages.home', $tenant);

        $this->assertSame('tenant.themes.moto.pages.home', $resolved);
    }

    public function test_resolves_default_theme_layer_for_default_theme_key(): void
    {
        $tenant = new Tenant(['theme_key' => 'default']);
        $resolved = app(TenantViewResolver::class)->resolve('pages.home', $tenant);

        $this->assertSame('tenant.themes.default.pages.home', $resolved);
    }

    public function test_unknown_safe_theme_falls_back_to_default_layer(): void
    {
        $tenant = new Tenant(['theme_key' => 'education']);
        $resolved = app(TenantViewResolver::class)->resolve('pages.home', $tenant);

        $this->assertSame('tenant.themes.default.pages.home', $resolved);
    }

    public function test_invalid_theme_key_characters_normalize_to_default(): void
    {
        $tenant = new Tenant(['theme_key' => '../evil']);
        $this->assertSame('default', $tenant->themeKey());

        $resolved = app(TenantViewResolver::class)->resolve('pages.home', $tenant);
        $this->assertSame('tenant.themes.default.pages.home', $resolved);
    }

    public function test_page_logical_name_falls_through_to_engine_when_no_theme_overrides(): void
    {
        $tenant = new Tenant(['theme_key' => 'moto']);
        $resolved = app(TenantViewResolver::class)->resolve('pages.page', $tenant);

        $this->assertSame('tenant.pages.page', $resolved);
    }

    public function test_null_tenant_uses_default_theme_chain(): void
    {
        $resolved = app(TenantViewResolver::class)->resolve('pages.home', null);

        $this->assertSame('tenant.themes.default.pages.home', $resolved);
    }
}
