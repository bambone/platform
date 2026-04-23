<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
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
