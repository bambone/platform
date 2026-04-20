<?php

namespace Tests\Unit\Services\Tenancy;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantExpertAutoFooterData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantExpertAutoFooterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_includes_home_nav_office_and_copyright(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Expert Tenant',
            'slug' => 'expert-footer-test',
            'status' => 'active',
        ]);

        TenantSetting::setForTenant((int) $tenant->id, 'general.site_name', 'Тестовый эксперт');
        TenantSetting::setForTenant((int) $tenant->id, 'contacts.public_office_address', 'г. Тест, ул. Примерная, 1', 'string');

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Программы',
            'slug' => 'programs',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => true,
            'main_menu_sort_order' => 10,
        ]);

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Правила аренды',
            'slug' => 'usloviya-arenda',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Политика конфиденциальности',
            'slug' => 'politika-konfidencialnosti',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $data = app(TenantExpertAutoFooterData::class)->build($tenant);

        $this->assertSame('г. Тест, ул. Примерная, 1', $data['office_address']);
        $this->assertSame('Тестовый эксперт', $data['copyright_holder']);
        $this->assertSame((int) now()->year, $data['year']);

        $labels = array_column($data['nav_items'], 'label');
        $this->assertContains('Главная', $labels);
        $this->assertContains('Программы', $labels);

        $this->assertCount(2, $data['legal_items']);
        $this->assertSame(route('terms'), $data['legal_items'][0]['url']);
        $this->assertSame(route('privacy'), $data['legal_items'][1]['url']);

        $this->assertArrayHasKey('footer_tagline', $data);
        $this->assertNotSame('', trim((string) $data['footer_tagline']));
    }

    public function test_build_falls_back_to_contacts_address_when_public_office_empty(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Address Fallback',
            'slug' => 'footer-address-fallback',
            'status' => 'active',
        ]);

        TenantSetting::setForTenant((int) $tenant->id, 'contacts.address', 'г. Сочи, ул. Морская, 1', 'string');

        $data = app(TenantExpertAutoFooterData::class)->build($tenant);

        $this->assertSame('г. Сочи, ул. Морская, 1', $data['office_address']);
    }

    public function test_build_uses_custom_footer_tagline_from_settings(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tagline Tenant',
            'slug' => 'footer-tagline-test',
            'status' => 'active',
        ]);

        TenantSetting::setForTenant((int) $tenant->id, 'general.footer_tagline', 'Кастомный текст подвала для теста.', 'string');

        $data = app(TenantExpertAutoFooterData::class)->build($tenant);

        $this->assertSame('Кастомный текст подвала для теста.', $data['footer_tagline']);
    }
}
