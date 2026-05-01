<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use App\Tenant\ExpertPr\MagasHeroDefaults;
use App\Tenant\Footer\FooterSectionType;
use App\Tenant\Footer\TenantFooterResolver;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Tenant\MagasExpertBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class MagasBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_default_run_creates_draft_pages_and_seo_not_indexable(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $this->assertGreaterThan(0, $tid);

        $privacy = DB::table('pages')->where('tenant_id', $tid)->where('slug', 'privacy')->first();
        $this->assertNotNull($privacy);
        $this->assertSame('draft', $privacy->status);

        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $this->assertGreaterThan(0, $homeId);

        $seo = DB::table('seo_meta')
            ->where('tenant_id', $tid)
            ->where('seoable_type', \App\Models\Page::class)
            ->where('seoable_id', $homeId)
            ->first();
        $this->assertNotNull($seo);
        $this->assertSame(0, (int) $seo->is_indexable);

        $hero = DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $homeId)
            ->where('section_key', 'expert_hero')
            ->first();
        $this->assertNotNull($hero);
        $heroData = json_decode((string) $hero->data_json, true);
        $this->assertIsArray($heroData);
        $heroUrl = (string) ($heroData['hero_image_url'] ?? '');
        $this->assertMatchesRegularExpression(
            '#^(https://sergeymagas\.(?:ru|com)/magas\.png|site/brand/magas-hero\.(png|jpg))$#',
            $heroUrl,
        );

        $faviconSource = base_path(str_replace('\\', DIRECTORY_SEPARATOR, 'docs/tenants_tz/magas/Prod-eng/favicon.ico'));
        if (is_file($faviconSource)) {
            $ts = TenantStorage::forTrusted($tid);
            $this->assertTrue(
                $ts->existsPublic('site/brand/favicon.ico'),
                'Magas bootstrap should copy docs favicon into tenant public storage when the source file exists.',
            );
            $url = trim((string) TenantSetting::getForTenant($tid, 'branding.favicon', ''));
            $path = trim((string) TenantSetting::getForTenant($tid, 'branding.favicon_path', ''));
            $this->assertTrue(
                $url !== '' || $path !== '',
                'Expected branding.favicon or branding.favicon_path after favicon sync.',
            );
        }

        $contactsFt = DB::table('tenant_footer_sections')
            ->where('tenant_id', $tid)->where('section_key', 'magas_footer_contacts_v1')->first();
        $this->assertNotNull($contactsFt);
        $this->assertSame(\App\Tenant\Footer\FooterSectionType::CONTACTS, $contactsFt->type);

        $linkGrp = DB::table('tenant_footer_sections')
            ->where('tenant_id', $tid)->where('section_key', 'magas_footer_link_groups_v1')->first();
        $this->assertNotNull($linkGrp);
        $this->assertSame(\App\Tenant\Footer\FooterSectionType::LINK_GROUPS, $linkGrp->type);
        $this->assertSame(9, (int) DB::table('tenant_footer_links')->where('section_id', $linkGrp->id)->count());
        $response = DB::table('tenant_footer_sections')
            ->where('tenant_id', $tid)->where('section_key', 'magas_footer_response_v1')->first();
        $this->assertNotNull($response);
        $this->assertSame(\App\Tenant\Footer\FooterSectionType::CONDITIONS_LIST, $response->type);

        $homeIdAssert = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $this->assertGreaterThan(0, $homeIdAssert);
        $this->assertNotNull(DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $homeIdAssert)->where('section_key', 'home_mid_cta')->first());
    }

    public function test_publish_keeps_placeholder_pages_noindex(): void
    {
        Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
            '--allow-placeholder-content' => true,
        ]);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');

        foreach (['privacy', 'terms', 'cases'] as $slug) {
            $pageId = (int) DB::table('pages')
                ->where('tenant_id', $tid)
                ->where('slug', $slug)
                ->value('id');
            $this->assertGreaterThan(0, $pageId, $slug);

            $seo = DB::table('seo_meta')
                ->where('tenant_id', $tid)
                ->where('seoable_type', \App\Models\Page::class)
                ->where('seoable_id', $pageId)
                ->first();
            $this->assertNotNull($seo, $slug);
            $this->assertSame(0, (int) $seo->is_indexable, $slug);
            $this->assertSame(0, (int) $seo->is_followable, $slug);
        }
    }

    public function test_publish_without_placeholder_keeps_cases_draft(): void
    {
        Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
        ]);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');

        $cases = DB::table('pages')
            ->where('tenant_id', $tid)
            ->where('slug', 'cases')
            ->first();

        $this->assertNotNull($cases);
        $this->assertSame('draft', $cases->status);
    }

    public function test_publish_indexes_home_but_keeps_privacy_noindex_without_placeholder_flag(): void
    {
        Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
        ]);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $seoHome = DB::table('seo_meta')
            ->where('tenant_id', $tid)
            ->where('seoable_id', $homeId)
            ->where('seoable_type', \App\Models\Page::class)
            ->first();
        $this->assertNotNull($seoHome);
        $this->assertSame(1, (int) $seoHome->is_indexable);

        $privacyRow = DB::table('pages')->where('tenant_id', $tid)->where('slug', 'privacy')->first();
        $this->assertNotNull($privacyRow);
        $this->assertSame('published', $privacyRow->status);

        $privacyId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'privacy')->value('id');
        $seoPrivacy = DB::table('seo_meta')
            ->where('tenant_id', $tid)
            ->where('seoable_id', $privacyId)
            ->where('seoable_type', \App\Models\Page::class)
            ->first();
        $this->assertNotNull($seoPrivacy);
        $this->assertSame(0, (int) $seoPrivacy->is_indexable);
    }

    public function test_magas_footer_resolves_as_full_after_bootstrap(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tenant = Tenant::query()->where('slug', MagasExpertBootstrap::SLUG)->firstOrFail();

        $footer = app(TenantFooterResolver::class)->resolve($tenant);

        $this->assertSame('full', $footer['mode']);

        $types = collect($footer['sections'] ?? [])->pluck('type')->all();

        $this->assertContains(FooterSectionType::CONTACTS, $types);
        $this->assertContains(FooterSectionType::LINK_GROUPS, $types);
        $this->assertContains(FooterSectionType::CONDITIONS_LIST, $types);
    }

    public function test_sergeymagas_com_is_only_primary_domain(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $primaries = DB::table('tenant_domains')
            ->where('tenant_id', $tid)
            ->where('is_primary', true)
            ->pluck('host')
            ->all();
        $this->assertSame(['sergeymagas.com'], array_values($primaries));
    }

    public function test_host_conflict_with_another_tenant_throws(): void
    {
        $otherTid = (int) DB::table('tenants')->insertGetId([
            'name' => 'Other',
            'slug' => 'other-magas-block',
            'brand_name' => 'Other',
            'theme_key' => 'expert_pr',
            'status' => 'active',
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tenant_domains')->insert([
            'tenant_id' => $otherTid,
            'host' => 'sergeymagas.com',
            'type' => \App\Models\TenantDomain::TYPE_CUSTOM,
            'is_primary' => true,
            'status' => 'active',
            'ssl_status' => \App\Models\TenantDomain::SSL_PENDING,
            'verified_at' => now(),
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        MagasExpertBootstrap::run(false, null, false, false);
    }

    public function test_second_run_with_publish_and_placeholder_promotes_existing_faq_rows(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $this->assertGreaterThan(0, DB::table('faqs')->where('tenant_id', $tid)->where('status', 'draft')->count());

        Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
            '--allow-placeholder-content' => true,
        ]);

        $this->assertSame(
            DB::table('faqs')->where('tenant_id', $tid)->count(),
            DB::table('faqs')->where('tenant_id', $tid)->where('status', 'published')->count(),
        );
    }

    public function test_canonical_id_rejects_invalid_value(): void
    {
        $exit = Artisan::call('tenant:magas:bootstrap', [
            '--canonical-id' => 'abc',
        ]);
        $this->assertSame(1, $exit);
    }

    public function test_publish_conflicts_with_force_draft(): void
    {
        $exit = Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
            '--force-draft' => true,
        ]);
        $this->assertSame(1, $exit);
    }

    /**
     * Без параметра командная строка не обязана «избегать» id=5: до Magas уже могли создаться сиды других строк.
     * Явный --canonical-id гарантированно вставляет в свободный слот выбранный PK.
     */
    public function test_canonical_id_inserts_requested_row_id_when_slot_free(): void
    {
        Artisan::call('tenant:magas:bootstrap', [
            '--canonical-id' => 88,
        ]);

        $tid = (int) DB::table('tenants')->where('slug', MagasExpertBootstrap::SLUG)->value('id');
        $this->assertSame(88, $tid);
    }

    /** Рендер публичной главной без View not found для typed footer (`expert-pr-full`). */
    public function test_magas_published_home_renders_full_footer_without_view_errors(): void
    {
        Artisan::call('tenant:magas:bootstrap', [
            '--publish' => true,
        ]);

        $response = $this->call('GET', 'http://sergeymagas.com/');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Sergei Magas', $body);
        $this->assertStringContainsString('Explore', $body);
        $this->assertStringContainsString('expert-pr-footer__contacts', $body);
        $this->assertStringContainsString('Response &amp; follow-up', $body);
    }

    public function test_cards_teaser_text_link_and_button_contract_in_default_partial(): void
    {
        $linkHtml = trim(View::make('tenant.themes.default.sections.cards-teaser', [
            'data' => [
                'heading' => '',
                'description' => '',
                'card_button_variant' => 'text_link',
                'cards' => [
                    [
                        'title' => 'Lane',
                        'text' => 'Copy',
                        'image' => null,
                        'button_text' => 'Explore media outreach',
                        'button_url' => '/services/media-outreach',
                    ],
                ],
            ],
        ])->render());

        $this->assertStringContainsString('Explore media outreach', $linkHtml);
        $this->assertMatchesRegularExpression('/(→|&rarr;|&#8594;|&#x2192;)/u', $linkHtml, 'text_link CTA должна содержать стрелку в HTML');

        $buttonHtml = trim(View::make('tenant.themes.default.sections.cards-teaser', [
            'data' => [
                'heading' => '',
                'description' => '',
                'card_button_variant' => 'button',
                'cards' => [
                    [
                        'title' => 'Svc',
                        'text' => 'Body',
                        'image' => null,
                        'button_text' => 'Details',
                        'button_url' => '/services/foo',
                    ],
                ],
            ],
        ])->render());

        $this->assertStringContainsString('rounded-lg border', $buttonHtml);
        $this->assertSame(0, preg_match('#→#u', $buttonHtml));
    }

    public function test_magas_hero_defaults_fill_persists_once_then_noops_when_already_hydrated(): void
    {
        Artisan::call('tenant:magas:bootstrap', []);

        $tid = (int) DB::table('tenants')->where('slug', MagasHeroDefaults::SLUG)->value('id');
        $this->assertGreaterThan(0, $tid);
        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $heroId = (int) DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $homeId)->where('section_key', 'expert_hero')
            ->value('id');

        DB::table('page_sections')->where('id', $heroId)->update([
            'data_json' => json_encode(array_merge(
                json_decode((string) DB::table('page_sections')->where('id', $heroId)->value('data_json'), true) ?: [],
                ['hero_image_url' => '', 'hero_image_alt' => ''],
            ), JSON_UNESCAPED_UNICODE),
        ]);

        $changed = app(MagasHeroDefaults::class)->fillMissingHomeHeroImage($tid);
        $this->assertTrue($changed);

        // Second run: DB already hydrated — no redundant write cycle for callers relying on truthiness.
        $this->assertFalse(app(MagasHeroDefaults::class)->fillMissingHomeHeroImage($tid));
    }
}
