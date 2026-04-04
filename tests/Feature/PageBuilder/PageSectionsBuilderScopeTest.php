<?php

namespace Tests\Feature\PageBuilder;

use App\Livewire\Tenant\PageSectionsBuilder;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class PageSectionsBuilderScopeTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    private function bindTenantContext(Tenant $tenant): void
    {
        $host = $this->tenancyHostForSlug((string) $tenant->slug);
        $domain = TenantDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant, $domain, false, $host));
    }

    public function test_non_home_builder_shows_main_plaque_and_empty_state_without_home_sections(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pbscope');
        $this->bindTenantContext($tenant);

        $home = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        $rules = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Правила аренды',
            'slug' => 'rules-pbscope',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $home->id,
            'section_key' => 'faq_1',
            'section_type' => 'faq',
            'title' => 'UNIQUE_HOME_ONLY_MARKER_PBSCOPE',
            'data_json' => ['items' => []],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        Livewire::test(PageSectionsBuilder::class, ['record' => $rules->fresh()])
            ->assertSee('Основной контент страницы', escape: false)
            ->assertSee('Правила аренды', escape: false)
            ->assertSee('Нет дополнительных секций', escape: false)
            ->assertSee('Добавить блок', escape: false)
            ->assertDontSee('UNIQUE_HOME_ONLY_MARKER_PBSCOPE', escape: false);
    }

    public function test_builder_lists_only_sections_for_current_page_in_order(): void
    {
        $tenant = $this->createTenantWithActiveDomain('pbscope2');
        $this->bindTenantContext($tenant);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'О нас',
            'slug' => 'about-pbscope2',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'text_section_1',
            'section_type' => 'text_section',
            'title' => 'FIRST_BLOCK_PBSCOPE2',
            'data_json' => [
                'title' => 'Раздел 1',
                'content' => '<p>A</p>',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'notice_box_2',
            'section_type' => 'notice_box',
            'title' => 'SECOND_BLOCK_PBSCOPE2',
            'data_json' => [
                'tone' => 'warning',
                'text' => '<p>B</p>',
            ],
            'sort_order' => 20,
            'is_visible' => false,
            'status' => 'draft',
        ]);

        $html = Livewire::test(PageSectionsBuilder::class, ['record' => $page->fresh()])
            ->html();

        $posFirst = strpos($html, 'FIRST_BLOCK_PBSCOPE2');
        $posSecond = strpos($html, 'SECOND_BLOCK_PBSCOPE2');
        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertLessThan($posSecond, $posFirst, 'First section should appear before second in DOM order');

        $this->assertStringContainsString('#1', $html);
        $this->assertStringContainsString('#2', $html);
        $this->assertStringContainsString('Скрыт', $html);
        $this->assertStringContainsString('На сайте', $html);
    }
}
