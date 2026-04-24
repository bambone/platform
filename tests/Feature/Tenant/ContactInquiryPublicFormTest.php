<?php

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\TenantServiceProgram;
use App\Models\TenantSetting;
use App\PageBuilder\PageSectionTypeRegistry;
use App\Tenant\BlackDuck\BlackDuckServiceProgramCatalog;
use App\Tenant\Expert\ServiceProgramType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class ContactInquiryPublicFormTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function host(string $slug): string
    {
        return $this->tenancyHostForSlug($slug);
    }

    private function makeContactsPageWithInquirySection(int $tenantId, array $dataJsonOverrides = []): PageSection
    {
        $page = Page::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Контакты',
            'slug' => 'contacts',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        $defaults = app(PageSectionTypeRegistry::class)->get('contact_inquiry')->defaultData();
        $data = array_merge($defaults, $dataJsonOverrides);

        return PageSection::query()->create([
            'tenant_id' => $tenantId,
            'page_id' => $page->id,
            'section_key' => 'contacts_inquiry',
            'section_type' => 'contact_inquiry',
            'title' => 'Форма',
            'data_json' => $data,
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);
    }

    public function test_contacts_page_renders_contact_inquiry_form(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-contacts', ['theme_key' => 'default']);
        $this->makeContactsPageWithInquirySection((int) $tenant->id);
        $h = $this->host('ci-contacts');

        $this->get('http://'.$h.'/contacts')
            ->assertOk()
            ->assertSee('data-rb-contact-inquiry-form', false)
            ->assertSee('Напишите нам', false);
    }

    public function test_contact_inquiry_rejects_vk_without_value(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-vk', ['theme_key' => 'default']);
        TenantSetting::setForTenant((int) $tenant->id, 'contacts.vk_url', 'https://vk.com/club_test', 'string');
        $section = $this->makeContactsPageWithInquirySection((int) $tenant->id);
        $h = $this->host('ci-vk');

        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', [
            'page_section_id' => $section->id,
            'name' => 'Иван',
            'phone' => '+79991112233',
            'message' => 'Вопрос по условиям достаточной длины',
            'preferred_contact_channel' => 'vk',
            'preferred_contact_value' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['preferred_contact_value']);
    }

    public function test_contact_inquiry_post_creates_crm_and_lead(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-post', ['theme_key' => 'default']);
        $section = $this->makeContactsPageWithInquirySection((int) $tenant->id);
        $h = $this->host('ci-post');

        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', [
            'page_section_id' => $section->id,
            'name' => 'Иван',
            'phone' => '+79991112233',
            'message' => 'Вопрос по условиям',
            'preferred_contact_channel' => 'phone',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('crm_requests', [
            'tenant_id' => $tenant->id,
            'request_type' => 'contact_page_inquiry',
            'source' => 'contacts_page',
        ]);

        $crm = CrmRequest::query()->where('request_type', 'contact_page_inquiry')->first();
        $this->assertNotNull($crm);
        $payload = $crm->payload_json;
        $this->assertIsArray($payload);
        $this->assertSame('contacts_form', $payload['source_type'] ?? null);
        $this->assertSame('/contacts', $payload['source_path'] ?? null);
    }

    public function test_contact_inquiry_validation_errors_are_422(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-val', ['theme_key' => 'default']);
        $section = $this->makeContactsPageWithInquirySection((int) $tenant->id);
        $h = $this->host('ci-val');

        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', [
            'page_section_id' => $section->id,
            'name' => '',
            'phone' => 'bad',
            'message' => 'ab',
            'preferred_contact_channel' => 'phone',
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'message']);
    }

    public function test_honeypot_does_not_create_crm_but_returns_ok(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-honey', ['theme_key' => 'default']);
        $section = $this->makeContactsPageWithInquirySection((int) $tenant->id);
        $h = $this->host('ci-honey');

        $before = CrmRequest::query()->count();

        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', [
            'page_section_id' => $section->id,
            'name' => 'Бот',
            'phone' => '+79991112233',
            'message' => 'Спам',
            'preferred_contact_channel' => 'phone',
            'website' => 'http://spam.example',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame($before, CrmRequest::query()->count());
    }

    public function test_rate_limit_returns_429(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-rate', ['theme_key' => 'default']);
        $section = $this->makeContactsPageWithInquirySection((int) $tenant->id);
        $h = $this->host('ci-rate');
        RateLimiter::clear('contact-inquiry:'.$tenant->id.':127.0.0.1');

        $payload = [
            'page_section_id' => $section->id,
            'name' => 'Тест',
            'phone' => '+79991112233',
            'message' => 'Сообщение достаточной длины',
            'preferred_contact_channel' => 'phone',
        ];

        for ($i = 0; $i < 8; $i++) {
            $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', $payload)->assertOk();
        }

        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', $payload)->assertStatus(429);
    }

    public function test_black_duck_contact_inquiry_requires_service_only_when_section_flag_set(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-bd-svc', ['theme_key' => 'black_duck']);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => 'ppf',
            'title' => 'PPF',
            'teaser' => 't',
            'description' => 'd',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'catalog_meta_json' => [
                'has_landing' => true,
                'booking_mode' => 'confirm',
            ],
        ]);
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Контакты',
            'slug' => 'contacts',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);
        $defaults = app(PageSectionTypeRegistry::class)->get('contact_inquiry')->defaultData();
        $defaults['requires_service_selector'] = true;
        $section = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'contacts_inquiry',
            'section_type' => 'contact_inquiry',
            'title' => 'Форма',
            'data_json' => $defaults,
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);
        $h = $this->host('ci-bd-svc');
        $payload = [
            'page_section_id' => $section->id,
            'name' => 'Иван',
            'phone' => '+79991112233',
            'message' => 'Вопрос по условиям достаточной длины',
            'preferred_contact_channel' => 'phone',
        ];
        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['inquiry_service_slug']);

        $options = BlackDuckServiceProgramCatalog::inquiryFormServiceOptions((int) $tenant->id);
        $this->assertNotSame([], $options, 'DB-first список услуг для формы — из tenant_service_programs');
        $this->assertContains('ppf', array_column($options, 'slug'));
        $slug = 'ppf';

        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', array_merge($payload, [
            'inquiry_service_slug' => $slug,
        ]))->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'contact_page_inquiry')->first();
        $this->assertNotNull($crm);
        $pj = $crm->payload_json;
        $this->assertIsArray($pj);
        $this->assertSame($slug, $pj['inquiry_service_slug'] ?? null);
    }

    public function test_black_duck_contact_inquiry_without_flag_does_not_require_service_slug_in_payload(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-bd-optout', ['theme_key' => 'black_duck']);
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Контакты',
            'slug' => 'contacts',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);
        $defaults = app(PageSectionTypeRegistry::class)->get('contact_inquiry')->defaultData();
        $defaults['requires_service_selector'] = false;
        $section = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'inquiry_min',
            'section_type' => 'contact_inquiry',
            'title' => 'Форма',
            'data_json' => $defaults,
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);
        $h = $this->host('ci-bd-optout');
        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', [
            'page_section_id' => $section->id,
            'name' => 'Иван',
            'phone' => '+79991112233',
            'message' => 'Короткое тестовое обращение без привязки к услуге',
            'preferred_contact_channel' => 'phone',
        ])->assertOk();

        $crm = CrmRequest::query()->where('request_type', 'contact_page_inquiry')->first();
        $this->assertNotNull($crm);
        $pj = $crm->payload_json;
        $this->assertIsArray($pj);
        $this->assertArrayNotHasKey('inquiry_service_slug', $pj);
    }

    public function test_black_duck_rejects_forged_service_slug_when_no_visible_programs_in_db_catalog(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ci-bd-empty-svc', ['theme_key' => 'black_duck']);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => 'hidden-only',
            'title' => 'Скрытая',
            'teaser' => 't',
            'description' => 'd',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => false,
            'is_featured' => false,
            'sort_order' => 0,
            'catalog_meta_json' => [
                'has_landing' => false,
                'booking_mode' => 'confirm',
            ],
        ]);
        $section = $this->makeContactsPageWithInquirySection((int) $tenant->id, [
            'requires_service_selector' => true,
        ]);
        $h = $this->host('ci-bd-empty-svc');
        $base = [
            'page_section_id' => $section->id,
            'name' => 'Иван',
            'phone' => '+79991112233',
            'message' => 'Сообщение достаточной длины для валидации',
            'preferred_contact_channel' => 'phone',
        ];
        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', $base)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->postJson('http://'.$h.'/api/tenant/contact-inquiry', array_merge($base, [
            'inquiry_service_slug' => 'ppf',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['inquiry_service_slug']);
    }
}
