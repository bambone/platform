<?php

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Models\Page;
use App\Models\PageSection;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class AdvocateEditorialTenantTest extends TestCase
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

    public function test_advocate_editorial_home_renders_page_builder_hero(): void
    {
        $tenant = $this->createTenantWithActiveDomain('advhome', ['theme_key' => 'advocate_editorial']);
        $host = $this->tenancyHostForSlug('advhome');

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
                'heading' => 'Уникальный заголовок адвоката — тест героя',
                'subheading' => 'Подзаголовок',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $this->getWithHost($host, '/')
            ->assertOk()
            ->assertSee('Уникальный заголовок адвоката — тест героя', false);
    }

    public function test_expert_inquiry_with_legal_services_domain_is_accepted(): void
    {
        $this->createTenantWithActiveDomain('advlegal', ['theme_key' => 'advocate_editorial']);
        $host = $this->tenancyHostForSlug('advlegal');

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Клиент',
            'phone' => '+79991112299',
            'goal_text' => 'Нужна консультация по уголовному делу',
            'preferred_contact_channel' => 'phone',
            'expert_domain' => 'legal_services',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('crm_requests', [
            'request_type' => 'expert_service_inquiry',
        ]);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $payload = $crm->payload_json;
        $this->assertIsArray($payload);
        $this->assertSame('legal_services', $payload['expert_domain'] ?? null);
    }
}
