<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Tenant\BlackDuckBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame(1, $c['version']);
        $this->assertIsArray($c['assets']);
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
