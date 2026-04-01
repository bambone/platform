<?php

namespace Tests\Feature\Tenant;

use App\Models\TenantSetting;
use App\Services\Analytics\AnalyticsSettingsPersistence;
use App\Services\Analytics\AnalyticsSnippetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantAnalyticsTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_public_home_has_no_analytics_snippets_in_testing_by_default(): void
    {
        $tenant = $this->createTenantWithActiveDomain('noan');
        $host = $this->tenancyHostForSlug('noan');

        TenantSetting::setForTenant($tenant->id, 'integrations.analytics', [
            'yandex_metrica' => [
                'enabled' => true,
                'counter_id' => '123456',
                'webvisor_enabled' => false,
                'clickmap_enabled' => false,
                'track_links_enabled' => false,
                'accurate_bounce_enabled' => false,
            ],
            'ga4' => [
                'enabled' => true,
                'measurement_id' => 'G-TESTPUBLIC1',
            ],
        ], 'json');
        Cache::flush();
        $this->flushTenantHostCache($host);

        $html = $this->call('GET', 'http://'.$host.'/')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringNotContainsString('mc.yandex.ru/metrika/tag.js', $html);
    }

    public function test_public_home_renders_snippets_when_testing_render_enabled(): void
    {
        config(['analytics.render_in_testing' => true]);

        $tenant = $this->createTenantWithActiveDomain('yesan');
        $host = $this->tenancyHostForSlug('yesan');

        TenantSetting::setForTenant($tenant->id, 'integrations.analytics', [
            'yandex_metrica' => [
                'enabled' => true,
                'counter_id' => '123456',
                'webvisor_enabled' => false,
                'clickmap_enabled' => true,
                'track_links_enabled' => false,
                'accurate_bounce_enabled' => false,
            ],
            'ga4' => [
                'enabled' => true,
                'measurement_id' => 'G-TESTPUBLIC1',
            ],
        ], 'json');
        Cache::flush();
        $this->flushTenantHostCache($host);

        $loaded = app(AnalyticsSettingsPersistence::class)->load($tenant->id);
        $this->assertTrue($loaded->hasRenderableGa4(), 'GA4 should be renderable from storage');
        $this->assertTrue($loaded->hasRenderableYandex(), 'Yandex should be renderable from storage');

        $html = $this->call('GET', 'http://'.$host.'/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-TESTPUBLIC1', $html);
        $this->assertStringContainsString('mc.yandex.ru/metrika/tag.js', $html);
        $this->assertStringContainsString('ym(123456', $html);
    }

    public function test_yandex_disabled_does_not_render_even_with_counter_in_storage(): void
    {
        config(['analytics.render_in_testing' => true]);

        $tenant = $this->createTenantWithActiveDomain('offym');
        $host = $this->tenancyHostForSlug('offym');

        TenantSetting::setForTenant($tenant->id, 'integrations.analytics', [
            'yandex_metrica' => [
                'enabled' => false,
                'counter_id' => '123456',
                'webvisor_enabled' => true,
                'clickmap_enabled' => true,
                'track_links_enabled' => true,
                'accurate_bounce_enabled' => true,
            ],
            'ga4' => [
                'enabled' => false,
                'measurement_id' => '',
            ],
        ], 'json');
        Cache::flush();
        $this->flushTenantHostCache($host);

        $html = $this->call('GET', 'http://'.$host.'/')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('mc.yandex.ru/metrika/tag.js', $html);
        $this->assertStringNotContainsString('ym(123456', $html);
    }

    public function test_tenant_isolation_on_public_home(): void
    {
        config(['analytics.render_in_testing' => true]);

        $ta = $this->createTenantWithActiveDomain('iso-a');
        $tb = $this->createTenantWithActiveDomain('iso-b');
        $hostA = $this->tenancyHostForSlug('iso-a');
        $hostB = $this->tenancyHostForSlug('iso-b');

        TenantSetting::setForTenant($ta->id, 'integrations.analytics', [
            'yandex_metrica' => [
                'enabled' => false,
                'counter_id' => '',
                'webvisor_enabled' => false,
                'clickmap_enabled' => false,
                'track_links_enabled' => false,
                'accurate_bounce_enabled' => false,
            ],
            'ga4' => [
                'enabled' => true,
                'measurement_id' => 'G-TENANTAAA',
            ],
        ], 'json');

        TenantSetting::setForTenant($tb->id, 'integrations.analytics', [
            'yandex_metrica' => [
                'enabled' => false,
                'counter_id' => '',
                'webvisor_enabled' => false,
                'clickmap_enabled' => false,
                'track_links_enabled' => false,
                'accurate_bounce_enabled' => false,
            ],
            'ga4' => [
                'enabled' => true,
                'measurement_id' => 'G-TENANTBBB',
            ],
        ], 'json');
        Cache::flush();
        $this->flushTenantHostCache($hostA);
        $this->flushTenantHostCache($hostB);

        $htmlA = $this->call('GET', 'http://'.$hostA.'/')->assertOk()->getContent();
        $htmlB = $this->call('GET', 'http://'.$hostB.'/')->assertOk()->getContent();

        $this->assertStringContainsString('G-TENANTAAA', $htmlA);
        $this->assertStringNotContainsString('G-TENANTBBB', $htmlA);
        $this->assertStringContainsString('G-TENANTBBB', $htmlB);
        $this->assertStringNotContainsString('G-TENANTAAA', $htmlB);
    }

    public function test_renderer_returns_empty_without_tenant_even_when_force_render(): void
    {
        config(['analytics.force_render' => true]);

        $renderer = app(AnalyticsSnippetRenderer::class);
        $html = $renderer->renderHeadHtml(Request::create('https://central.test/'));

        $this->assertSame('', $html);
    }

    public function test_tenant_filament_login_does_not_render_public_analytics_snippets(): void
    {
        config(['analytics.render_in_testing' => true]);

        $tenant = $this->createTenantWithActiveDomain('filno');
        $host = $this->tenancyHostForSlug('filno');

        TenantSetting::setForTenant($tenant->id, 'integrations.analytics', [
            'yandex_metrica' => [
                'enabled' => true,
                'counter_id' => '123456',
                'webvisor_enabled' => false,
                'clickmap_enabled' => false,
                'track_links_enabled' => false,
                'accurate_bounce_enabled' => false,
            ],
            'ga4' => [
                'enabled' => true,
                'measurement_id' => 'G-FILNO123',
            ],
        ], 'json');
        Cache::flush();
        $this->flushTenantHostCache($host);

        $html = $this->call('GET', 'http://'.$host.'/admin/login')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringNotContainsString('mc.yandex.ru/metrika/tag.js', $html);
    }
}
