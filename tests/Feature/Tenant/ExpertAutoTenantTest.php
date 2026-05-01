<?php

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\TenantServiceProgram;
use App\Models\TenantSetting;
use App\Support\Typography\RussianTypography;
use App\Tenant\Expert\ServiceProgramType;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class ExpertAutoTenantTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    protected function getWithHost(string $host, string $path = '/'): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('GET', 'http://'.$host.$path);
    }

    public function test_expert_inquiry_creates_crm_request_with_payload(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expertapi', ['theme_key' => 'expert_auto']);
        TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'parking',
            'title' => 'Парковка',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
        ]);
        $host = $this->tenancyHostForSlug('expertapi');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Нужна помощь с парковкой во дворе',
            'preferred_contact_channel' => 'phone',
            'program_slug' => 'parking',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('crm_requests', [
            'request_type' => 'expert_service_inquiry',
        ]);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $payload = $crm->payload_json;
        $this->assertIsArray($payload);
        $this->assertSame('driving_instruction', $payload['expert_domain'] ?? null);
        $this->assertContains('parking', $payload['intent_tags'] ?? []);
    }

    public function test_expert_inquiry_rejects_too_long_preferred_schedule(): void
    {
        $this->createTenantWithActiveDomain('expertsched', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expertsched');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Тест',
            'preferred_contact_channel' => 'phone',
            'preferred_schedule' => str_repeat('а', 121),
        ])->assertStatus(422)->assertJsonValidationErrors(['preferred_schedule']);
    }

    public function test_expert_inquiry_accepts_freeform_preferred_schedule(): void
    {
        $this->createTenantWithActiveDomain('expertsched-free', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expertsched-free');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Тест',
            'preferred_contact_channel' => 'phone',
            'preferred_schedule' => 'вечером, будни',
        ])->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $this->assertSame('вечером, будни', $crm->payload_json['preferred_schedule'] ?? null);
    }

    public function test_expert_inquiry_accepts_time_interval_preferred_schedule(): void
    {
        $this->createTenantWithActiveDomain('expertschedok', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expertschedok');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Тест',
            'preferred_contact_channel' => 'phone',
            'preferred_schedule' => '18:00 – 21:00',
        ])->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $this->assertSame('18:00 – 21:00', $crm->payload_json['preferred_schedule'] ?? null);
    }

    public function test_expert_inquiry_program_enrollment_marks_source_and_payload(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-enroll', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expert-enroll');

        $program = TenantServiceProgram::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'city-comfort',
            'title' => 'Городской комфорт',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Запись на программу: «Городской комфорт»',
            'preferred_contact_channel' => 'phone',
            'program_slug' => 'city-comfort',
            'source_type' => 'program_enrollment',
            'source_page' => '/programs',
            'program_id' => $program->id,
            'privacy_accepted' => '1',
        ])->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $this->assertSame('programs_page', $crm->source);
        $payload = $crm->payload_json;
        $this->assertIsArray($payload);
        $this->assertSame('program_enrollment', $payload['source_type'] ?? null);
        $this->assertSame('/programs', $payload['source_page'] ?? null);
        $this->assertSame((int) $program->id, (int) ($payload['program_id'] ?? 0));
    }

    public function test_expert_inquiry_enrollment_cta_marks_expert_enrollment_source(): void
    {
        $this->createTenantWithActiveDomain('expert-enroll-cta', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expert-enroll-cta');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Запись со страницы о тренере',
            'preferred_contact_channel' => 'phone',
            'source_type' => 'enrollment_cta',
            'source_page' => '/o-trener',
            'source_context' => 'trainer_bio_primary_cta',
            'privacy_accepted' => '1',
        ])->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $this->assertSame('expert_enrollment', $crm->source);
        $payload = $crm->payload_json;
        $this->assertIsArray($payload);
        $this->assertSame('enrollment_cta', $payload['source_type'] ?? null);
        $this->assertSame('/o-trener', $payload['source_page'] ?? null);
        $this->assertSame('trainer_bio_primary_cta', $payload['source_context'] ?? null);
    }

    public function test_expert_inquiry_program_enrollment_requires_privacy_acceptance(): void
    {
        $this->createTenantWithActiveDomain('expert-priv', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expert-priv');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Тест',
            'preferred_contact_channel' => 'phone',
            'source_type' => 'program_enrollment',
            'source_page' => '/programs',
        ])->assertStatus(422)->assertJsonValidationErrors(['privacy_accepted']);
    }

    public function test_expert_inquiry_rejects_vk_without_contact_value(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-vk-empty', ['theme_key' => 'expert_auto']);
        TenantSetting::setForTenant((int) $tenant->id, 'contacts.vk_url', 'https://vk.com/club_test', 'string');
        $host = $this->tenancyHostForSlug('expert-vk-empty');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Нужна консультация',
            'preferred_contact_channel' => 'vk',
            'preferred_contact_value' => '',
            'privacy_accepted' => '1',
        ])->assertStatus(422)->assertJsonValidationErrors(['preferred_contact_value']);
    }

    public function test_expert_inquiry_rejects_vk_garbage_value(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-vk-bad', ['theme_key' => 'expert_auto']);
        TenantSetting::setForTenant((int) $tenant->id, 'contacts.vk_url', 'https://vk.com/club_test', 'string');
        $host = $this->tenancyHostForSlug('expert-vk-bad');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Нужна консультация',
            'preferred_contact_channel' => 'vk',
            'preferred_contact_value' => 'vk',
            'privacy_accepted' => '1',
        ])->assertStatus(422)->assertJsonValidationErrors(['preferred_contact_value']);
    }

    public function test_expert_inquiry_accepts_vk_normalized_contact(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expert-vk-ok', ['theme_key' => 'expert_auto']);
        TenantSetting::setForTenant((int) $tenant->id, 'contacts.vk_url', 'https://vk.com/club_test', 'string');
        $host = $this->tenancyHostForSlug('expert-vk-ok');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Нужна консультация',
            'preferred_contact_channel' => 'vk',
            'preferred_contact_value' => 'vk.com/durov',
            'privacy_accepted' => '1',
        ])->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $this->assertSame('vk', $crm->preferred_contact_channel);
        $this->assertSame('https://vk.com/durov', $crm->preferred_contact_value);
    }

    public function test_expert_inquiry_honeypot_does_not_create_crm(): void
    {
        $this->createTenantWithActiveDomain('expert-hp', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expert-hp');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Спам',
            'phone' => '+79991112299',
            'goal_text' => 'Тест',
            'preferred_contact_channel' => 'phone',
            'website' => 'http://evil.example',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(0, CrmRequest::query()->where('request_type', 'expert_service_inquiry')->count());
    }

    public function test_expert_home_renders_page_builder_hero(): void
    {
        $tenant = $this->createTenantWithActiveDomain('experthome', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('experthome');

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
            'section_key' => 'expert_hero',
            'section_type' => 'expert_hero',
            'title' => 'Hero',
            'data_json' => [
                'heading' => 'Уникальный заголовок эксперта — тест героя',
                'subheading' => 'Подзаголовок',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $heading = 'Уникальный заголовок эксперта — тест героя';

        $this->getWithHost($host, '/')
            ->assertOk()
            ->assertSee(RussianTypography::tiePrepositionsToNextWord($heading), false);
    }

    public function test_pricing_cards_section_not_rendered_without_programs_or_manual(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expertpricingempty', ['theme_key' => 'expert_auto']);
        $host = $this->tenancyHostForSlug('expertpricingempty');

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
            'section_key' => 'pricing_cards',
            'section_type' => 'pricing_cards',
            'title' => 'Цены',
            'data_json' => [
                'heading' => 'Уникальный заголовок цен для пустой секции',
                'subheading' => '',
                'note' => '',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $this->getWithHost($host, '/')
            ->assertOk()
            ->assertDontSee('Уникальный заголовок цен для пустой секции', false);
    }

    public function test_resolve_pricing_cards_respects_include_slugs(): void
    {
        $tenant = $this->createTenantWithActiveDomain('expertpricingslug', ['theme_key' => 'expert_auto']);

        foreach (['keep-me', 'drop-me'] as $slug) {
            TenantServiceProgram::query()->create([
                'tenant_id' => $tenant->id,
                'slug' => $slug,
                'title' => 'T '.$slug,
                'program_type' => ServiceProgramType::Program->value,
                'is_visible' => true,
                'sort_order' => $slug === 'keep-me' ? 1 : 2,
            ]);
        }

        $resolved = TenantServiceProgram::resolvePricingCardsSection((int) $tenant->id, [
            'include_slugs' => ['keep-me'],
        ]);

        $this->assertCount(1, $resolved['programs']);
        $this->assertSame('keep-me', $resolved['programs']->first()->slug);
    }
}
