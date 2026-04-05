<?php

namespace Tests\Feature\SEO;

use App\Models\Category;
use App\Models\Faq;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantSeoSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function seedTenantWithDomain(string $host, string $slug = 'seov2'): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'SEO V2',
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => $host,
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);

        TenantSetting::setForTenant($tenant->id, 'general.domain', 'https://'.$host, 'string');
        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'Test Moto Rent', 'string');
        TenantSetting::setForTenant($tenant->id, 'seo.indexing_enabled', true, 'boolean');
        TenantSetting::setForTenant($tenant->id, 'seo.sitemap_enabled', true, 'boolean');

        return $tenant;
    }

    public function test_motorcycles_view_has_title_and_matching_canonical_and_og_url(): void
    {
        $tenant = $this->seedTenantWithDomain('seov2.apex.test');
        $cat = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cat',
            'slug' => 'cat',
        ]);
        Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $cat->id,
            'name' => 'List Bike',
            'slug' => 'list-bike',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);

        $html = $this->call('GET', 'http://seov2.apex.test/motorcycles')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<title>[^<]+<\/title>/', $html);
        $this->assertStringNotContainsString(config('app.name'), $html);
        $this->assertStringContainsString('ItemList', $html);

        if (preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m)
            && preg_match('/<meta property="og:url" content="([^"]+)"/', $html, $o)) {
            $this->assertSame($m[1], $o[1]);
        } else {
            $this->fail('canonical or og:url not found');
        }
    }

    public function test_home_includes_organization_and_website_json_ld(): void
    {
        $tenant = $this->seedTenantWithDomain('seohome.apex.test', 'seohome');

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $html = $this->call('GET', 'http://seohome.apex.test/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('Organization', $html);
        $this->assertStringContainsString('WebSite', $html);
        $this->assertStringContainsString('"@context":"https://schema.org"', $html);
        $this->assertStringNotContainsString('__contextArgs', $html);
    }

    public function test_motorcycle_show_includes_product_json_ld(): void
    {
        $tenant = $this->seedTenantWithDomain('seomoto.apex.test', 'seomoto');

        $cat = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Road',
            'slug' => 'road',
        ]);

        Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $cat->id,
            'name' => 'Honda Test',
            'slug' => 'honda-test',
            'short_description' => 'Тестовый байк для SEO.',
            'price_per_day' => 3500,
            'status' => 'available',
            'show_in_catalog' => true,
        ]);

        $html = $this->call('GET', 'http://seomoto.apex.test/moto/honda-test')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('Product', $html);
        $this->assertStringContainsString('Offer', $html);
        $this->assertStringContainsString('"priceCurrency":"RUB"', $html);
    }

    public function test_faq_page_includes_faqpage_json_ld_when_faqs_exist(): void
    {
        $tenant = $this->seedTenantWithDomain('seofaq.apex.test', 'seofaq');

        Faq::query()->create([
            'tenant_id' => $tenant->id,
            'question' => 'Вопрос?',
            'answer' => '<p>Ответ.</p>',
            'status' => 'published',
            'sort_order' => 1,
        ]);

        $html = $this->call('GET', 'http://seofaq.apex.test/faq')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('FAQPage', $html);
        $this->assertStringContainsString('Question', $html);
    }

    public function test_llms_txt_is_served_on_tenant_host(): void
    {
        $this->seedTenantWithDomain('seollms.apex.test', 'seollms');

        $this->call('GET', 'http://seollms.apex.test/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Test Moto Rent', false)
            ->assertSee('seollms.apex.test', false)
            ->assertSee('Полезные страницы', false)
            ->assertSee('публичный сайт компании', false);
    }

    public function test_tenant_route_overrides_merge_into_resolved_title(): void
    {
        $tenant = $this->seedTenantWithDomain('seorouteov.apex.test', 'seorouteov');
        TenantSetting::setForTenant($tenant->id, 'seo.route_overrides', json_encode([
            'faq' => ['title' => 'OVERRIDE FAQ TITLE — {site_name}'],
        ], JSON_UNESCAPED_UNICODE));

        $html = $this->call('GET', 'http://seorouteov.apex.test/faq')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('OVERRIDE FAQ TITLE — Test Moto Rent', $html);
    }

    public function test_sitemap_lists_only_current_tenant_paths(): void
    {
        $t1 = $this->seedTenantWithDomain('seomap1.apex.test', 'seomap1');
        $t2 = $this->seedTenantWithDomain('seomap2.apex.test', 'seomap2');

        Motorcycle::query()->create([
            'tenant_id' => $t1->id,
            'category_id' => Category::query()->create([
                'tenant_id' => $t1->id,
                'name' => 'C1',
                'slug' => 'c1',
            ])->id,
            'name' => 'Only T1',
            'slug' => 'only-t1',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);

        Motorcycle::query()->create([
            'tenant_id' => $t2->id,
            'category_id' => Category::query()->create([
                'tenant_id' => $t2->id,
                'name' => 'C2',
                'slug' => 'c2',
            ])->id,
            'name' => 'Only T2',
            'slug' => 'only-t2',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);

        $xml = $this->call('GET', 'http://seomap1.apex.test/sitemap.xml')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('only-t1', $xml);
        $this->assertStringNotContainsString('only-t2', $xml);
    }

    public function test_sitemap_excludes_noindex_motorcycle(): void
    {
        $tenant = $this->seedTenantWithDomain('seonoindex.apex.test', 'seonoindex');
        $cat = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cx',
            'slug' => 'cx',
        ]);

        $hidden = Motorcycle::query()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $cat->id,
            'name' => 'Hidden Bike',
            'slug' => 'hidden-bike',
            'status' => 'available',
            'show_in_catalog' => true,
        ]);

        SeoMeta::query()->create([
            'tenant_id' => $tenant->id,
            'seoable_type' => Motorcycle::class,
            'seoable_id' => $hidden->id,
            'is_indexable' => false,
            'is_followable' => true,
        ]);

        $xml = $this->call('GET', 'http://seonoindex.apex.test/sitemap.xml')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('hidden-bike', $xml);
    }

    public function test_robots_disallow_all_when_indexing_disabled(): void
    {
        $tenant = $this->seedTenantWithDomain('seorobots.apex.test', 'seorobots');
        TenantSetting::setForTenant($tenant->id, 'seo.indexing_enabled', false, 'boolean');
        Cache::flush();

        $this->call('GET', 'http://seorobots.apex.test/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false);
    }
}
