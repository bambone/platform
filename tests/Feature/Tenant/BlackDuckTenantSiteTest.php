<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use App\Tenant\BlackDuck\BlackDuckMediaRole;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Tenant\BlackDuckBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BlackDuckTenantSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['tenancy.central_domains' => ['localhost', '127.0.0.1']]);
        Cache::flush();
        $this->seed(RolePermissionSeeder::class);
        (new BlackDuckBootstrap)->run();
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $catalogKey = TenantStorage::forTrusted($tid)->publicPath(BlackDuckMediaCatalog::CATALOG_LOGICAL);
        $diskName = TenantStorageDisks::publicDiskName();
        if (Storage::disk($diskName)->exists($catalogKey)) {
            Storage::disk($diskName)->delete($catalogKey);
        }
        Artisan::call('tenant:black-duck:refresh-settings', ['tenant' => BlackDuckBootstrap::SLUG]);
        Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--force' => true,
        ]);
    }

    public function test_public_home_renders_for_black_duck_host(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $this->assertSame(BlackDuckContentConstants::CANONICAL_TENANT_ID, $tid);
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $this->assertNotSame('', $host);
        $response = $this->call('GET', 'http://'.$host.'/');

        $response->assertOk();
        $response->assertSee('Black Duck', false);
        $response->assertSee('912', false);
    }

    public function test_home_does_not_include_pruned_or_inline_lead_form_sections(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $this->assertGreaterThan(0, $homeId);
        $keys = DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $homeId)
            ->pluck('section_key')
            ->all();
        foreach (['expert_lead_form', 'vehicle_class', 'package_matrix'] as $bad) {
            $this->assertNotContains($bad, $keys, 'Pruned home section should not exist: '.$bad);
        }
    }

    public function test_home_service_hub_uses_preview_matrix_size(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $row = DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $homeId)
            ->where('section_key', 'service_hub')
            ->first();
        $this->assertNotNull($row);
        $d = is_string($row->data_json) ? json_decode($row->data_json, true) : $row->data_json;
        $this->assertIsArray($d);
        $items = is_array($d['items'] ?? null) ? $d['items'] : [];
        $this->assertCount(
            count(BlackDuckContentConstants::HOME_SERVICE_PREVIEW_SLUGS),
            $items,
        );
    }

    public function test_home_renders_primary_lead_and_works_cta_hrefs(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $r = $this->call('GET', 'http://'.$host.'/');
        $r->assertOk();
        $h = (string) $r->getContent();
        $this->assertStringContainsString(BlackDuckContentConstants::PRIMARY_LEAD_URL, $h);
        $this->assertStringContainsString(BlackDuckContentConstants::WORKS_PAGE_URL, $h);
    }

    public function test_public_home_does_not_link_to_expert_inquiry_hash(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $h = (string) $this->call('GET', 'http://'.$host.'/')->getContent();
        $this->assertStringNotContainsString('#expert-inquiry', $h);
    }

    public function test_ppf_landing_does_not_render_expert_lead_mega_block(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $r = $this->call('GET', 'http://'.$host.'/ppf');
        $r->assertOk();
        $html = (string) $r->getContent();
        $this->assertStringNotContainsString('expert-inquiry-block', $html);
        $this->assertStringNotContainsString('id="expert-inquiry', $html);
    }

    public function test_raboty_renders_and_uses_lead_href_in_cta(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $r = $this->call('GET', 'http://'.$host.'/raboty');
        $r->assertOk();
        $this->assertStringContainsString(BlackDuckContentConstants::PRIMARY_LEAD_URL, (string) $r->getContent());
    }

    public function test_home_hides_both_result_sections_when_no_curated_media(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        foreach (['before_after', 'case_cards'] as $key) {
            $vis = (bool) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $homeId)
                ->where('section_key', $key)
                ->value('is_visible');
            $this->assertFalse($vis, 'Without stored proof images, '.$key.' should be hidden');
        }
    }

    public function test_ppf_service_proof_section_hidden_without_catalog_gallery(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $pageId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'ppf')->value('id');
        $this->assertGreaterThan(0, $pageId);
        $vis = (bool) DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $pageId)
            ->where('section_key', 'service_proof')
            ->value('is_visible');
        $this->assertFalse($vis);
    }

    public function test_media_catalog_loads_for_tenant(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $c = BlackDuckMediaCatalog::loadOrEmpty($tid);
        $this->assertGreaterThanOrEqual(1, $c['version']);
        $this->assertIsArray($c['assets']);
    }

    public function test_raboty_renders_without_video_when_no_curated_pair(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $html = (string) $this->call('GET', 'http://'.$host.'/raboty')->getContent();
        $this->assertStringNotContainsString('<video', $html);
    }

    public function test_home_proof_and_ppf_service_proof_with_curated_local_catalog(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $jpg = self::minimalJpegBytes();
        $this->assertNotSame('', $jpg);
        $ts = TenantStorage::forTrusted($tid);
        $this->assertTrue($ts->putPublic('site/brand/proof/t-home-b.jpg', $jpg, ['ContentType' => 'image/jpeg', 'visibility' => 'public']));
        $this->assertTrue($ts->putPublic('site/brand/proof/t-home-a.jpg', $jpg, ['ContentType' => 'image/jpeg', 'visibility' => 'public']));
        $this->assertTrue($ts->putPublic('site/brand/proof/t-ppf-1.jpg', $jpg, ['ContentType' => 'image/jpeg', 'visibility' => 'public']));
        $stubMp4 = "00000018\x66\x74\x79\x70\x69\x73\x6F\x6D\x00\x00\x02\x00\x69\x73\x6F\x6D\x69\x73\x6F\x32\x6D\x70\x34\x31";
        $this->assertTrue($ts->putPublic('site/brand/proof/t-works.mp4', $stubMp4, ['ContentType' => 'video/mp4', 'visibility' => 'public']));
        $this->assertTrue($ts->putPublic('site/brand/proof/t-works-poster.jpg', $jpg, ['ContentType' => 'image/jpeg', 'visibility' => 'public']));
        $this->assertTrue($ts->putPublic('site/brand/proof/t-works-g1.jpg', $jpg, ['ContentType' => 'image/jpeg', 'visibility' => 'public']));
        $this->assertTrue($ts->putPublic('site/brand/proof/t-hub-ppf.jpg', $jpg, ['ContentType' => 'image/jpeg', 'visibility' => 'public']));
        $this->assertTrue($ts->putPublic('site/brand/proof/t-ppf-vid.mp4', $stubMp4, ['ContentType' => 'video/mp4', 'visibility' => 'public']));
        $this->assertTrue($ts->putPublic('site/brand/proof/t-ppf-vid-poster.jpg', $jpg, ['ContentType' => 'image/jpeg', 'visibility' => 'public']));
        $assets = [
            [
                'role' => BlackDuckMediaRole::HomeProofBefore->value,
                'before_after_group' => 't1',
                'logical_path' => 'site/brand/proof/t-home-b.jpg',
                'sort_order' => 0,
                'caption' => 'До',
            ],
            [
                'role' => BlackDuckMediaRole::HomeProofAfter->value,
                'before_after_group' => 't1',
                'logical_path' => 'site/brand/proof/t-home-a.jpg',
                'sort_order' => 0,
                'caption' => 'После',
            ],
            [
                'role' => BlackDuckMediaRole::ServiceGallery->value,
                'service_slug' => 'ppf',
                'logical_path' => 'site/brand/proof/t-ppf-1.jpg',
                'sort_order' => 0,
                'caption' => 'Кромка',
            ],
            [
                'role' => BlackDuckMediaRole::WorksFeaturedVideo->value,
                'logical_path' => 'site/brand/proof/t-works.mp4',
                'poster_logical_path' => 'site/brand/proof/t-works-poster.jpg',
                'sort_order' => 0,
            ],
            [
                'role' => BlackDuckMediaRole::WorksGallery->value,
                'service_slug' => 'ppf',
                'logical_path' => 'site/brand/proof/t-works-g1.jpg',
                'sort_order' => 0,
                'caption' => 'Кромка в работе',
                'tags' => ['PPF'],
            ],
            [
                'role' => BlackDuckMediaRole::HomeServiceCard->value,
                'service_slug' => 'ppf',
                'logical_path' => 'site/brand/proof/t-hub-ppf.jpg',
                'sort_order' => 0,
            ],
            [
                'role' => BlackDuckMediaRole::ServiceFeaturedVideo->value,
                'service_slug' => 'ppf',
                'logical_path' => 'site/brand/proof/t-ppf-vid.mp4',
                'poster_logical_path' => 'site/brand/proof/t-ppf-vid-poster.jpg',
                'sort_order' => 0,
            ],
        ];
        $this->assertTrue(BlackDuckMediaCatalog::saveCatalog($tid, BlackDuckMediaCatalog::SCHEMA_VERSION, $assets));
        Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--force' => true,
        ]);

        $homeId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'home')->value('id');
        $baVis = (bool) DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $homeId)->where('section_key', 'before_after')
            ->value('is_visible');
        $ccVis = (bool) DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $homeId)->where('section_key', 'case_cards')
            ->value('is_visible');
        $this->assertTrue($baVis);
        $this->assertFalse($ccVis);

        $homeHtml = (string) $this->call('GET', 'http://'.$host.'/')->getContent();
        $this->assertStringContainsString('bd-ba-heading', $homeHtml);
        $this->assertStringContainsString(BlackDuckContentConstants::PRIMARY_LEAD_URL, $homeHtml);

        $hub = DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $homeId)->where('section_key', 'service_hub')
            ->value('data_json');
        $hubData = is_string($hub) ? json_decode($hub, true) : $hub;
        $this->assertIsArray($hubData);
        $this->assertCount(count(BlackDuckContentConstants::HOME_SERVICE_PREVIEW_SLUGS), $hubData['items'] ?? []);

        $raboty = (string) $this->call('GET', 'http://'.$host.'/raboty')->getContent();
        $this->assertStringContainsString('<video', $raboty);
        $this->assertStringContainsString('data-bd-portfolio-root', $raboty);
        $this->assertStringContainsString('data-bd-portfolio-filter="tag:PPF"', $raboty);
        $this->assertStringContainsString('bd-works-lightbox', $raboty);
        $this->assertStringContainsString('data-bd-lightbox-open=', $raboty);

        $rabotyPageId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'raboty')->value('id');
        $this->assertTrue((bool) DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $rabotyPageId)->where('section_key', 'works_portfolio')
            ->value('is_visible'));

        $uslugiId = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'uslugi')->value('id');
        $uslugiHub = DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $uslugiId)->where('section_key', 'service_hub')
            ->value('data_json');
        $uslugiData = is_string($uslugiHub) ? json_decode($uslugiHub, true) : $uslugiHub;
        $this->assertIsArray($uslugiData);
        $ppfCard = collect($uslugiData['items'] ?? [])->firstWhere('cta_url', '/ppf');
        $this->assertIsArray($ppfCard);
        $this->assertStringContainsString('t-hub-ppf.jpg', (string) ($ppfCard['image_url'] ?? ''));

        $pageIdPpf = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'ppf')->value('id');
        $proofVis = (bool) DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $pageIdPpf)->where('section_key', 'service_proof')
            ->value('is_visible');
        $this->assertTrue($proofVis);
        $heroJson = DB::table('page_sections')
            ->where('tenant_id', $tid)->where('page_id', $pageIdPpf)->where('section_key', 'hero')
            ->value('data_json');
        $heroData = is_string($heroJson) ? json_decode($heroJson, true) : $heroJson;
        $this->assertIsArray($heroData);
        $this->assertTrue((bool) ($heroData['video_deferred'] ?? false));
        $ppfHtml = (string) $this->call('GET', 'http://'.$host.'/ppf')->getContent();
        $this->assertStringContainsString('t-ppf-1.jpg', $ppfHtml);
        $this->assertStringContainsString(BlackDuckContentConstants::PRIMARY_LEAD_URL, $ppfHtml);
    }

    /**
     * @return non-empty-string
     */
    private static function minimalJpegBytes(): string
    {
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor(1, 1);
            if ($img !== false) {
                imagecolorallocate($img, 10, 20, 30);
                ob_start();
                imagejpeg($img);
                imagedestroy($img);
                $b = (string) ob_get_clean();
                if ($b !== '') {
                    return $b;
                }
            }
        }

        return (string) base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAoHBwgHBgoICAgLCgoLDhgQDg0NDh0VFhEYIx8lJCIfIiEmKzcvJik0KSEiMEExNDk7Pj4+JS5ESUM8SDc9Pjv/2wBDAQoLCw4NDhwQEBw7KCIoOzs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozv/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=',
            true,
        );
    }

    public function test_seeded_bookable_instant_and_confirmation_services_exist(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $this->assertGreaterThan(0, $tid);

        $this->assertDatabaseHas('bookable_services', [
            'tenant_id' => $tid,
            'slug' => 'detailing-wash',
            'requires_confirmation' => false,
        ]);
        $this->assertDatabaseHas('bookable_services', [
            'tenant_id' => $tid,
            'slug' => 'interior-detailing-quote',
            'requires_confirmation' => true,
        ]);
    }

    public function test_expert_inquiry_quote_request_saves_utm_and_source_page_in_payload(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Тест',
            'phone' => '+79991112233',
            'goal_text' => 'Нужен расчёт PPF',
            'preferred_contact_channel' => 'phone',
            'privacy_accepted' => true,
            'inquiry_intent' => 'price_quote',
            'crm_request_type' => 'question_request',
            'utm_source' => 'unit',
            'utm_medium' => 'test',
            'source_page' => '/ppf',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('crm_requests', [
            'tenant_id' => $tid,
            'request_type' => 'quote_request',
            'utm_source' => 'unit',
        ]);

        $crm = CrmRequest::query()->where('tenant_id', $tid)->orderByDesc('id')->first();
        $this->assertNotNull($crm);
        $p = $crm->payload_json;
        $this->assertIsArray($p);
        $this->assertSame('/ppf', $p['source_page'] ?? null);
        $this->assertSame('question_request', $p['client_crm_type_hint'] ?? null);
        $this->assertSame('price_quote', $p['inquiry_intent'] ?? null);
    }

    public function test_bookable_services_api_lists_instant_wash(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');

        $r = $this->getJson('http://'.$host.'/api/tenant/scheduling/bookable-services');
        $r->assertOk();
        $slugs = collect($r->json('services'))->pluck('slug')->all();
        $this->assertContains('detailing-wash', $slugs);
    }

    public function test_slots_api_returns_entries_for_instant_wash(): void
    {
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $host = (string) DB::table('tenant_domains')->where('tenant_id', $tid)->value('host');
        $washId = (int) DB::table('bookable_services')
            ->where('tenant_id', $tid)
            ->where('slug', 'detailing-wash')
            ->value('id');
        $this->assertGreaterThan(0, $washId);

        $this->travelTo('2026-06-01 10:00:00');

        $r = $this->getJson(
            'http://'.$host.'/api/tenant/scheduling/bookable-services/'.$washId.'/slots?from=2026-06-01&to=2026-06-02'
        );
        $r->assertOk();
        $r->assertJsonStructure(['slots', 'warnings']);
        $this->assertNotEmpty($r->json('slots'));
    }
}
