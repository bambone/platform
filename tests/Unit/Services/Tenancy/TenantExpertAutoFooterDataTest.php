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

        $data = app(TenantExpertAutoFooterData::class)->build($tenant);

        $this->assertSame('г. Тест, ул. Примерная, 1', $data['office_address']);
        $this->assertSame('Тестовый эксперт', $data['copyright_holder']);
        $this->assertSame((int) now()->year, $data['year']);

        $labels = array_column($data['nav_items'], 'label');
        $this->assertContains('Главная', $labels);
        $this->assertContains('Программы', $labels);
    }
}
