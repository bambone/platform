<?php

namespace Tests\Feature\TenantSiteSetup;

use App\Filament\Tenant\Pages\TenantSiteSetupCenterPage;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\TenantSetting;
use App\Models\TenantSetupSession;
use App\Models\User;
use App\Tenant\CurrentTenant;
use App\TenantSiteSetup\SetupItemRegistry;
use App\TenantSiteSetup\SetupItemStateService;
use App\TenantSiteSetup\SetupJourneyBuilder;
use App\TenantSiteSetup\SetupProfileRepository;
use App\TenantSiteSetup\SetupProgressCache;
use App\TenantSiteSetup\SetupSessionService;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantSiteSetupSessionTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_advance_to_next_moves_current_item(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $this->assertTrue(TenantSiteSetupFeature::enabled());

        $tenant = $this->createTenantWithActiveDomain('ts_sess', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );

        $this->actingAs($user);

        $sessions = app(SetupSessionService::class);
        $session = $sessions->startOrResume($tenant, $user);
        $first = $session->current_item_key;
        $this->assertNotNull($first);

        $sessions->advanceToNext($tenant, $user);
        $session->refresh();
        $this->assertNotSame($first, $session->current_item_key);
    }

    public function test_snoozed_item_excluded_from_journey(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_snoo', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $keysBefore = app(SetupJourneyBuilder::class)->visibleStepKeys($tenant, $user);
        $this->assertNotEmpty($keysBefore);

        app(SetupItemStateService::class)->markSnoozed($tenant, $user, $keysBefore[0], 'test', null);

        $keysAfter = app(SetupJourneyBuilder::class)->visibleStepKeys($tenant, $user);
        $this->assertNotContains($keysBefore[0], $keysAfter);
    }

    public function test_overlay_payload_includes_session_action_url(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_pay', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        app(SetupSessionService::class)->startOrResume($tenant, $user);

        $host = $this->tenancyHostForSlug('ts_pay');
        $this->get('http://'.$host.'/admin/site-setup-profile');
        $payload = app(SetupSessionService::class)->overlayPayload($tenant, $user, request());
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('session_action_url', $payload);
        $this->assertStringContainsString('tenant-site-setup/session', (string) $payload['session_action_url']);
        $this->assertArrayHasKey('can_snooze', $payload);
        $this->assertArrayHasKey('target_url', $payload);
        $this->assertArrayHasKey('target_title', $payload);
        $this->assertArrayHasKey('target_item_key', $payload);
        $this->assertArrayHasKey('on_target_route', $payload);
        $this->assertArrayHasKey('can_complete_here', $payload);
        $this->assertArrayHasKey('primary_is_target_navigation', $payload);
        $this->assertArrayHasKey('guided_next_hint', $payload);
        $this->assertSame('save_then_next', $payload['guided_next_hint']);
        $this->assertFalse($payload['on_target_route']);
        $this->assertFalse($payload['can_complete_here']);
        $this->assertNotEmpty($payload['primary_is_target_navigation']);
        $this->assertIsArray($payload['page_builder_auto_open']);
        $this->assertArrayHasKey('enabled', $payload['page_builder_auto_open']);
        $this->assertFalse($payload['page_builder_auto_open']['enabled']);
    }

    public function test_overlay_payload_realigns_when_current_step_completed_by_data(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_rl', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'Название сайта достаточно длинное');
        TenantSetting::setForTenant($tenant->id, 'branding.logo', 'https://example.com/logo.png');
        TenantSetting::setForTenant($tenant->id, 'general.short_description', 'short');
        SetupProgressCache::forget((int) $tenant->id);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        app(SetupSessionService::class)->startOrResume($tenant, $user);
        TenantSetupSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('session_status', 'active')
            ->update(['current_item_key' => 'settings.tagline_or_short_description']);

        $p1 = app(SetupSessionService::class)->overlayPayload($tenant, $user, request());
        $this->assertIsArray($p1);
        $this->assertSame('settings.tagline_or_short_description', $p1['current_item_key']);

        TenantSetting::setForTenant($tenant->id, 'general.short_description', str_repeat('а', 12));
        SetupProgressCache::forget((int) $tenant->id);

        $p2 = app(SetupSessionService::class)->overlayPayload($tenant, $user, request());
        $this->assertIsArray($p2);
        $this->assertNotSame('settings.tagline_or_short_description', $p2['current_item_key']);
    }

    public function test_overlay_payload_auto_open_disabled_for_hero_cta_step(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_ao_cta', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->firstOrFail();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        app(SetupSessionService::class)->startOrResume($tenant, $user);
        TenantSetupSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('session_status', 'active')
            ->update(['current_item_key' => 'pages.home.hero_cta_or_contact_block']);

        $payload = app(SetupSessionService::class)->overlayPayload($tenant, $user, request());
        $this->assertIsArray($payload);
        $this->assertFalse($payload['page_builder_auto_open']['enabled']);
    }

    public function test_overlay_payload_auto_open_enabled_for_hero_title_when_home_has_hero_section(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_ao_hero', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);
        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'hero',
            'section_type' => 'hero',
            'title' => 'Hero',
            'data_json' => [],
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->firstOrFail();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        app(SetupSessionService::class)->startOrResume($tenant, $user);
        TenantSetupSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('session_status', 'active')
            ->update(['current_item_key' => 'pages.home.hero_title']);

        $payload = app(SetupSessionService::class)->overlayPayload($tenant, $user, request());
        $this->assertIsArray($payload);
        $this->assertTrue($payload['page_builder_auto_open']['enabled']);
        $this->assertSame('hero_editor_for_primary_field', $payload['page_builder_auto_open']['reason']);
        $this->assertSame(1600, $payload['page_builder_auto_open']['prefer_primary_target_ms']);
    }

    public function test_start_or_resume_reuses_active_session(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_reuse', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $svc = app(SetupSessionService::class);
        $first = $svc->startOrResume($tenant, $user);
        $second = $svc->startOrResume($tenant, $user);
        $this->assertSame((int) $first->id, (int) $second->id);
    }

    public function test_profile_page_with_active_session_shows_honest_overlay_copy(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_copy', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        app(SetupSessionService::class)->startOrResume($tenant, $user);
        $host = $this->tenancyHostForSlug('ts_copy');
        $response = $this->get('http://'.$host.'/admin/site-setup-profile');
        $response->assertOk();
        $response->assertSee('Быстрый запуск:', false);
        $response->assertSee('Следующий шаг:', false);
    }

    public function test_profile_repository_merged_includes_defaults(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ts_prof', ['theme_key' => 'expert_auto']);
        $merged = app(SetupProfileRepository::class)->getMerged($tenant->id);
        $this->assertArrayHasKey('business_focus', $merged);
        $this->assertArrayHasKey('primary_goal', $merged);
        $this->assertSame(1, $merged['schema_version']);
    }

    public function test_next_completing_guided_queue_redirects_to_overview_with_flash(): void
    {
        config(['features.tenant_site_setup_framework' => true]);
        $tenant = $this->createTenantWithActiveDomain('ts_queue_done', ['theme_key' => 'expert_auto']);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $keys = app(SetupJourneyBuilder::class)->visibleStepKeys($tenant, $user);
        $this->assertNotEmpty($keys);

        $defs = SetupItemRegistry::definitions();
        $itemStates = app(SetupItemStateService::class);
        if (count($keys) > 1) {
            $last = array_pop($keys);
            foreach ($keys as $k) {
                $def = $defs[$k];
                $itemStates->markCompletedBySystem($tenant, $k, $def->categoryKey, null, $def->filamentRouteName);
            }
            SetupProgressCache::forget((int) $tenant->id);
        }

        $sessions = app(SetupSessionService::class);
        $sessions->startFreshGuidedSession($tenant, $user);

        $host = $this->tenancyHostForSlug('ts_queue_done');
        $response = $this->post('http://'.$host.'/admin/tenant-site-setup/session', [
            'action' => 'next',
            '_token' => csrf_token(),
        ]);

        $response->assertSessionHas('site_setup_guided_completed');
        $response->assertRedirect(TenantSiteSetupCenterPage::getUrl());
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }
}
