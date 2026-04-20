<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Публичный подвал мото-сайта: для темы {@code default} (и {@code expert_auto}) данные из {@see \App\Services\Tenancy\TenantExpertAutoFooterData}.
 */
class TenantPublicMotoFooterTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_default_theme_home_renders_moto_footer_markup(): void
    {
        $tenant = $this->createTenantWithActiveDomain('footermoto');
        $this->assertSame('default', $tenant->themeKey());

        $host = $this->tenancyHostForSlug('footermoto');
        $html = $this->call('GET', 'http://'.$host.'/')->assertOk()->getContent();

        $this->assertStringContainsString('tenant-site-footer-moto', $html);
        $this->assertStringContainsString('role="contentinfo"', $html);
        $this->assertStringContainsString('©', $html);
    }

    public function test_expert_auto_theme_renders_same_footer_component(): void
    {
        $tenant = $this->createTenantWithActiveDomain('footerexpert');
        Tenant::query()->whereKey($tenant->id)->update(['theme_key' => 'expert_auto']);

        $host = $this->tenancyHostForSlug('footerexpert');
        $html = $this->call('GET', 'http://'.$host.'/')->assertOk()->getContent();

        $this->assertStringContainsString('tenant-site-footer-moto', $html);
    }

    public function test_moto_bundled_theme_renders_footer_like_motolevins_demo(): void
    {
        $tenant = $this->createTenantWithActiveDomain('footermototheme');
        Tenant::query()->whereKey($tenant->id)->update(['theme_key' => 'moto']);

        $host = $this->tenancyHostForSlug('footermototheme');
        $html = $this->call('GET', 'http://'.$host.'/')->assertOk()->getContent();

        $this->assertStringContainsString('tenant-site-footer-moto', $html);
        $this->assertStringContainsString('role="contentinfo"', $html);
    }

    public function test_advocate_theme_does_not_render_moto_footer(): void
    {
        $tenant = $this->createTenantWithActiveDomain('footeradv');
        Tenant::query()->whereKey($tenant->id)->update(['theme_key' => 'advocate_editorial']);

        $host = $this->tenancyHostForSlug('footeradv');
        $html = $this->call('GET', 'http://'.$host.'/')->assertOk()->getContent();

        $this->assertStringNotContainsString('tenant-site-footer-moto', $html);
    }
}
