<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformSetting;
use App\Services\Analytics\AnalyticsSnippetRenderer;
use App\Services\Analytics\PlatformMarketingAnalyticsPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlatformMarketingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_marketing_home_renders_snippets_on_central_domain_when_testing_render_enabled(): void
    {
        config(['analytics.render_in_testing' => true]);

        PlatformSetting::set(PlatformMarketingAnalyticsPersistence::SETTING_KEY, [
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
                'measurement_id' => 'G-PMTEST001',
            ],
        ], 'json');
        Cache::flush();

        $loaded = app(PlatformMarketingAnalyticsPersistence::class)->load();
        $this->assertTrue($loaded->hasRenderableGa4());
        $this->assertTrue($loaded->hasRenderableYandex());

        $html = $this->call('GET', 'http://apex.test/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-PMTEST001', $html);
        $this->assertStringContainsString('<!-- Yandex.Metrika counter -->', $html);
        $this->assertStringContainsString('https://mc.yandex.ru/metrika/tag.js?id=123456', $html);
        $this->assertStringContainsString("ym(123456, 'init',", $html);
        $this->assertStringContainsString('https://mc.yandex.ru/watch/123456', $html);
        $posBody = stripos($html, '<body');
        $this->assertNotFalse($posBody);
        $this->assertStringNotContainsString('mc.yandex.ru/watch/', substr($html, 0, $posBody));
    }

    public function test_marketing_home_has_no_snippets_without_platform_settings(): void
    {
        config(['analytics.render_in_testing' => true]);

        $html = $this->call('GET', 'http://apex.test/')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringNotContainsString('mc.yandex.ru/metrika/tag.js?', $html);
    }

    public function test_renderer_force_render_uses_platform_settings_on_central_host(): void
    {
        config(['analytics.force_render' => true]);

        PlatformSetting::set(PlatformMarketingAnalyticsPersistence::SETTING_KEY, [
            'yandex_metrica' => [
                'enabled' => true,
                'counter_id' => '987654',
                'webvisor_enabled' => false,
                'clickmap_enabled' => false,
                'track_links_enabled' => false,
                'accurate_bounce_enabled' => false,
            ],
            'ga4' => [
                'enabled' => false,
                'measurement_id' => '',
            ],
        ], 'json');
        Cache::flush();

        $renderer = app(AnalyticsSnippetRenderer::class);
        $html = $renderer->renderHeadHtml(Request::create('https://apex.test/'));

        $this->assertStringContainsString('mc.yandex.ru/metrika/tag.js?id=987654', $html);
        $this->assertStringContainsString("ym(987654, 'init',", $html);
        $this->assertStringNotContainsString('mc.yandex.ru/watch/', $html);
    }
}
