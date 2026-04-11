<?php

namespace Tests\Feature\PageBuilder;

use App\Livewire\Tenant\PageSectionsBuilder;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\PageBuilder\PageSectionTypeRegistry;
use App\Services\PageBuilder\PageSectionOperationsService;
use App\Services\PageBuilder\SectionViewResolver;
use App\Tenant\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class PageSectionContentCatalogTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    /** @var list<string> */
    private const CONTENT_TYPE_IDS = [
        'hero',
        'structured_text',
        'text_section',
        'content_faq',
        'list_block',
        'info_cards',
        'contacts_info',
        'data_table',
        'notice_box',
    ];

    private function bindTenantContext(Tenant $tenant): void
    {
        $host = $this->tenancyHostForSlug((string) $tenant->slug);
        $domain = TenantDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->app->instance(CurrentTenant::class, new CurrentTenant($tenant, $domain, false, $host));
    }

    private function makePage(Tenant $tenant, string $slug): Page
    {
        return Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'P '.$slug,
            'slug' => $slug,
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'show_in_main_menu' => false,
            'main_menu_sort_order' => 0,
        ]);
    }

    public function test_home_catalog_includes_landing_hero_not_only_content(): void
    {
        $reg = app(PageSectionTypeRegistry::class);
        $tenant = $this->createTenantWithActiveDomain('cat-home');
        $home = $this->makePage($tenant, 'home');

        $ids = array_map(fn ($b) => $b->id(), $reg->forPage($home, 'default'));
        $this->assertContains('hero', $ids);
        $this->assertContains('cta', $ids);
        $this->assertContains('motorcycle_catalog', $ids);
        $this->assertNotContains('structured_text', $ids);
    }

    public function test_expert_auto_home_landing_excludes_motorcycle_catalog(): void
    {
        $reg = app(PageSectionTypeRegistry::class);
        $tenant = $this->createTenantWithActiveDomain('cat-expert-auto');
        $home = $this->makePage($tenant, 'home');

        $idsExpert = array_map(fn ($b) => $b->id(), $reg->forPage($home, 'expert_auto'));
        $this->assertNotContains('motorcycle_catalog', $idsExpert);
        $this->assertContains('expert_hero', $idsExpert);
        $this->assertContains('pricing_cards', $idsExpert);

        $idsDefault = array_map(fn ($b) => $b->id(), $reg->forPage($home, 'default'));
        $this->assertContains('motorcycle_catalog', $idsDefault);
        $this->assertNotContains('expert_hero', $idsDefault);
    }

    public function test_non_home_catalog_includes_hero_and_content_blocks(): void
    {
        $reg = app(PageSectionTypeRegistry::class);
        $tenant = $this->createTenantWithActiveDomain('cat-rules');
        $rules = $this->makePage($tenant, 'rules-cat');

        $ids = array_map(fn ($b) => $b->id(), $reg->forPage($rules, 'default'));
        $this->assertContains('hero', $ids);
        $this->assertNotContains('cta', $ids);
        $this->assertNotContains('gallery', $ids);
        $this->assertNotContains('cards_teaser', $ids);
        $this->assertNotContains('motorcycle_catalog', $ids);
        foreach (self::CONTENT_TYPE_IDS as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_type_allowed_on_page_matches_catalog(): void
    {
        $reg = app(PageSectionTypeRegistry::class);
        $tenant = $this->createTenantWithActiveDomain('cat-allow');
        $rules = $this->makePage($tenant, 'doc');
        $home = $this->makePage($tenant, 'home');

        $this->assertTrue($reg->typeAllowedOnPage('hero', $rules, 'default'));
        $this->assertTrue($reg->typeAllowedOnPage('structured_text', $rules, 'default'));
        $this->assertTrue($reg->typeAllowedOnPage('hero', $home, 'default'));
        $this->assertFalse($reg->typeAllowedOnPage('structured_text', $home, 'default'));
    }

    public function test_create_typed_section_rejects_landing_type_on_non_home(): void
    {
        $tenant = $this->createTenantWithActiveDomain('cat-svc');
        $page = $this->makePage($tenant, 'legal');
        $svc = app(PageSectionOperationsService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Section type is not allowed for this page.');
        $svc->createTypedSection($page, 'cta', [
            'title' => 'X',
            'status' => 'published',
            'is_visible' => true,
            'data_json' => [
                'heading' => 'H',
                'body' => 'B',
                'button_text' => 'Go',
                'button_url' => 'https://example.test',
            ],
        ], $tenant->id);
    }

    public function test_create_typed_section_allows_cta_on_home(): void
    {
        $tenant = $this->createTenantWithActiveDomain('cat-home-cta');
        $home = $this->makePage($tenant, 'home');
        $svc = app(PageSectionOperationsService::class);
        $svc->createTypedSection($home, 'cta', [
            'title' => 'C',
            'status' => 'published',
            'is_visible' => true,
            'data_json' => [
                'heading' => 'H',
                'body' => 'B',
                'button_text' => 'Go',
                'button_url' => 'https://example.test',
            ],
        ], $tenant->id);

        $this->assertDatabaseHas('page_sections', [
            'page_id' => $home->id,
            'section_type' => 'cta',
        ]);
    }

    public function test_content_blueprints_have_contract_fields(): void
    {
        $reg = app(PageSectionTypeRegistry::class);
        foreach (self::CONTENT_TYPE_IDS as $id) {
            $bp = $reg->get($id);
            $this->assertNotSame('', $bp->viewLogicalName());
            $this->assertNotSame([], $bp->defaultData());
            $this->assertNotSame([], $bp->formComponents());
            $preview = $bp->previewSummary($bp->defaultData());
            $this->assertIsString($preview);
        }
    }

    public function test_content_section_views_resolve_and_render(): void
    {
        $tenant = $this->createTenantWithActiveDomain('cat-render');
        $page = $this->makePage($tenant, 'render-p');
        $resolver = app(SectionViewResolver::class);
        $reg = app(PageSectionTypeRegistry::class);

        foreach (self::CONTENT_TYPE_IDS as $typeId) {
            $section = PageSection::query()->create([
                'tenant_id' => $tenant->id,
                'page_id' => $page->id,
                'section_key' => $typeId.'_t',
                'section_type' => $typeId,
                'title' => 'T',
                'data_json' => $reg->get($typeId)->defaultData(),
                'sort_order' => 10,
                'is_visible' => true,
                'status' => 'published',
            ]);
            $viewName = $resolver->resolveViewName($section, $tenant);
            $this->assertNotNull($viewName, "No view for {$typeId}");
            $html = View::make($viewName, [
                'section' => $section,
                'data' => $section->data_json ?? [],
            ])->render();
            $this->assertIsString($html);
            $this->assertNotSame('', trim($html));
        }
    }

    public function test_legacy_landing_section_on_non_home_still_resolves_for_public_render(): void
    {
        $tenant = $this->createTenantWithActiveDomain('cat-legacy');
        $page = $this->makePage($tenant, 'old-landing');
        $section = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'cta_legacy',
            'section_type' => 'cta',
            'title' => 'Old',
            'data_json' => [],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);
        $name = app(SectionViewResolver::class)->resolveViewName($section, $tenant);
        $this->assertNotNull($name);
    }

    public function test_start_add_allows_hero_on_non_home(): void
    {
        $tenant = $this->createTenantWithActiveDomain('cat-lw');
        $this->bindTenantContext($tenant);
        $page = $this->makePage($tenant, 'nw');

        Livewire::test(PageSectionsBuilder::class, ['record' => $page])
            ->call('startAdd', 'hero')
            ->assertSet('showEditor', true)
            ->assertSet('activeTypeId', 'hero');
    }

    public function test_non_home_builder_html_shows_content_catalog_including_hero(): void
    {
        $tenant = $this->createTenantWithActiveDomain('cat-lw-html');
        $this->bindTenantContext($tenant);
        $page = $this->makePage($tenant, 'policy-html');

        $html = Livewire::test(PageSectionsBuilder::class, ['record' => $page])->html();

        $this->assertStringContainsString("startAdd('structured_text', null)", $html);
        $this->assertStringContainsString("startAdd('content_faq', null)", $html);
        $this->assertStringContainsString("startAdd('hero', null)", $html);
    }
}
