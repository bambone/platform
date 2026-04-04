<?php

namespace Tests\Feature\PageBuilder;

use App\Livewire\Tenant\PageSectionsBuilder;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\PageBuilder\PageSectionOperationsService;
use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class PageSectionsBuilderReorderTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    private function bindTenantContext(Tenant $tenant): void
    {
        $host = $this->tenancyHostForSlug((string) $tenant->slug);
        $domain = TenantDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant, $domain, false, $host));
    }

    public function test_livewire_reorder_sections_updates_sort_order(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-reorder');
        $this->bindTenantContext($tenant);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reorder test',
            'slug' => 'reorder-pb',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        $a = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'text_section_1',
            'section_type' => 'text_section',
            'title' => 'A',
            'data_json' => ['title' => 'A', 'content' => '<p>a</p>'],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);
        $b = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'notice_box_1',
            'section_type' => 'notice_box',
            'title' => 'B',
            'data_json' => ['tone' => 'info', 'text' => '<p>b</p>'],
            'sort_order' => 20,
            'is_visible' => true,
            'status' => 'published',
        ]);

        Livewire::test(PageSectionsBuilder::class, ['record' => $page->fresh()])
            ->call('reorderSections', [[(string) $b->id, (string) $a->id]])
            ->assertOk();

        $a->refresh();
        $b->refresh();
        $this->assertGreaterThan($a->sort_order, $b->sort_order);
    }

    public function test_service_reorder_sections_rejects_invalid_payload(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pb-reorder-svc');
        $this->bindTenantContext($tenant);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Reorder svc',
            'slug' => 'reorder-svc',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        $a = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'text_section_1',
            'section_type' => 'text_section',
            'title' => 'A',
            'data_json' => ['title' => 'A', 'content' => '<p>a</p>'],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $svc = app(PageSectionOperationsService::class);
        $this->expectException(\RuntimeException::class);
        $svc->reorderSections($page->fresh(), [(string) $a->id, '999999'], $tenant->id);
    }
}
