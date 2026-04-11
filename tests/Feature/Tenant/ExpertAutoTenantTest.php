<?php

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\TenantServiceProgram;
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
        $this->createTenantWithActiveDomain('expertapi', ['theme_key' => 'expert_auto']);
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
                'heading' => 'Уникальный заголовок эксперта для теста',
                'subheading' => 'Подзаголовок',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $this->getWithHost($host, '/')
            ->assertOk()
            ->assertSee('Уникальный заголовок эксперта для теста', false);
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
