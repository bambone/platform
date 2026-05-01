<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Product\Settings\MarketingContentResolver;
use App\Tenant\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Relies on phpunit.xml env: TENANCY_CENTRAL_DOMAINS, PLATFORM_HOST, TENANCY_ROOT_DOMAIN (apex.test host set).
 */
class HostRoutingSplitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mirrors typography in {@see resources/views/platform/marketing/partials/home-hero.blade.php}
     * ({!! str_replace(...) !!}).
     */
    private static function platformMarketingHeroHeadlineForAssert(string $headline): string
    {
        return str_replace(
            [' для ', ' с ', ' в ', ' и '],
            [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;'],
            $headline,
        );
    }

    /**
     * Mirrors hero subtitle typography in {@see resources/views/platform/marketing/partials/home-hero.blade.php}.
     */
    private static function platformMarketingHeroSubtitleForAssert(string $subtitle): string
    {
        return str_replace(
            [' для ', ' с ', ' в ', ' и ', ' — '],
            [' для&nbsp;', ' с&nbsp;', ' в&nbsp;', ' и&nbsp;', '&nbsp;— '],
            $subtitle,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    protected function getWithHost(string $host, string $path = '/'): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('GET', 'http://'.$host.$path);
    }

    public function test_central_domain_root_returns_marketing_landing(): void
    {
        $pm = app(MarketingContentResolver::class)->resolved();
        $variant = $pm['hero_variant'] ?? 'c';
        $headline = $pm['hero'][$variant] ?? $pm['hero']['c'];

        $this->getWithHost('apex.test', '/')
            ->assertOk()
            ->assertSee(self::platformMarketingHeroHeadlineForAssert((string) $headline), false)
            ->assertSee(self::platformMarketingHeroSubtitleForAssert((string) ($pm['hero_subtitle'] ?? '')), false)
            ->assertSee($pm['cta']['primary'], false)
            ->assertSee($pm['cta']['secondary'], false)
            ->assertSee($pm['cta']['discuss'], false);
    }

    public function test_central_domain_robots_txt_is_plain_and_lists_sitemap(): void
    {
        $this->getWithHost('apex.test', '/robots.txt')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *', false)
            ->assertSee('User-agent: OAI-SearchBot', false)
            ->assertSee('Sitemap: http://apex.test/sitemap.xml', false);
    }

    public function test_central_domain_sitemap_xml_lists_core_urls(): void
    {
        $this->getWithHost('apex.test', '/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>http://apex.test/</loc>', false)
            ->assertSee('<loc>http://apex.test/pricing</loc>', false);
    }

    public function test_central_domain_llms_txt_is_plain(): void
    {
        $this->getWithHost('apex.test', '/llms.txt')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSee('# RentBase', false)
            ->assertSee('http://apex.test/faq', false);
    }

    public function test_platform_host_root_redirects_guest_to_login(): void
    {
        $this->getWithHost('platform.apex.test', '/')
            ->assertRedirect('http://platform.apex.test/login');
    }

    public function test_platform_host_resolver_is_non_tenant_without_tenant(): void
    {
        $current = app(TenantResolver::class)->resolve('platform.apex.test');

        $this->assertTrue($current->isNonTenantHost);
        $this->assertNull($current->tenant);
    }

    public function test_tenant_subdomain_root_is_tenant_public_not_marketing(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Split Pub',
            'slug' => 'splitpub',
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'splitpub.apex.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);

        Cache::flush();

        $pm = app(MarketingContentResolver::class)->resolved();
        $variant = $pm['hero_variant'] ?? 'c';
        $centralHeroPlain = $pm['hero'][$variant] ?? $pm['hero']['c'];

        $this->getWithHost('splitpub.apex.test', '/')
            ->assertOk()
            ->assertDontSee(self::platformMarketingHeroHeadlineForAssert((string) $centralHeroPlain), false);
    }

    public function test_tenant_subdomain_admin_login_is_not_404(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Split Adm',
            'slug' => 'splitadm',
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'splitadm.apex.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);

        Cache::flush();

        $response = $this->getWithHost('splitadm.apex.test', '/admin/login');
        $this->assertNotSame(404, $response->getStatusCode(), 'tenant admin login should not be 404');
    }

    public function test_central_domain_admin_login_returns_404(): void
    {
        $this->getWithHost('apex.test', '/admin/login')->assertNotFound();
    }

    public function test_platform_host_admin_login_is_not_tenant_admin_flow(): void
    {
        $this->getWithHost('platform.apex.test', '/admin/login')->assertNotFound();
    }
}
