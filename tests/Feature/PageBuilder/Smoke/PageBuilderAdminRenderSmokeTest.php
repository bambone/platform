<?php

namespace Tests\Feature\PageBuilder\Smoke;

use App\Livewire\Tenant\PageSectionsBuilder;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Livewire HTML regression for tenant page sections builder (not E2E): catalog, main plaque, section rows; контентные страницы включают hero среди блоков.
 *
 * @group page-builder-smoke
 */
class PageBuilderAdminRenderSmokeTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function bindTenantContext(Tenant $tenant): void
    {
        $host = $this->tenancyHostForSlug((string) $tenant->slug);
        $domain = TenantDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant, $domain, false, $host));
    }

    public function test_non_home_builder_catalog_main_plaque_section_row_includes_hero(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pbsmoke-admin');
        $this->bindTenantContext($tenant);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin smoke policy',
            'slug' => 'admin-smoke-policy',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'notice_box_1',
            'section_type' => 'notice_box',
            'title' => 'NB row',
            'data_json' => [
                'title' => 'Notice',
                'text' => '<p>PB_ADMIN_SMOKE_SECTION_ROW_9K2M</p>',
                'tone' => 'info',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $html = Livewire::test(PageSectionsBuilder::class, ['record' => $page->fresh()])
            ->assertSee('Добавить блок', escape: false)
            ->assertSee('Основной контент страницы', escape: false)
            ->assertSee('PB_ADMIN_SMOKE_SECTION_ROW_9K2M', escape: false)
            ->assertSee("startAdd('structured_text', null)", false)
            ->assertSee("startAdd('content_faq', null)", false)
            ->assertSee("startAdd('hero', null)", false)
            ->assertSee('Базовые', escape: false)
            ->html();

        $this->assertStringContainsString('#1', $html);
    }
}
