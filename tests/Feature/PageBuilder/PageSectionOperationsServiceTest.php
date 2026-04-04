<?php

namespace Tests\Feature\PageBuilder;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\PageBuilder\PageSectionOperationsService;
use App\Services\PageBuilder\SectionViewResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class PageSectionOperationsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function tenantWithDomain(string $sub): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'T '.$sub,
            'slug' => $sub,
            'status' => 'active',
            'theme_key' => 'default',
        ]);
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => $sub.'.apex.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
            'verified_at' => now(),
            'activated_at' => now(),
        ]);
        Cache::flush();

        return $tenant->fresh();
    }

    public function test_create_typed_section_assigns_generated_key_and_type(): void
    {
        $tenant = $this->tenantWithDomain('pbcreate');
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'About',
            'slug' => 'about-pb',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $svc = app(PageSectionOperationsService::class);
        $svc->createTypedSection($page, 'notice_box', [
            'title' => 'Важно',
            'status' => 'published',
            'is_visible' => true,
            'data_json' => [
                'title' => 'Условия',
                'text' => '<p>Текст уведомления</p>',
                'tone' => 'info',
            ],
        ], $tenant->id);

        $row = PageSection::query()->where('page_id', $page->id)->firstOrFail();
        $this->assertSame('notice_box', $row->section_type);
        $this->assertMatchesRegularExpression('/^notice_box_\d+$/', $row->section_key);
    }

    public function test_patch_section_meta_block_title_updates_structured_text_data_json(): void
    {
        $tenant = $this->tenantWithDomain('pbpatchbt');
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'P',
            'slug' => 'pbpatchbt-page',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $section = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'structured_text_1',
            'section_type' => 'structured_text',
            'title' => 'List label',
            'data_json' => [
                'title' => 'Old block',
                'content' => '<p>Hi</p>',
                'max_width' => 'prose',
            ],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        app(PageSectionOperationsService::class)->patchSectionMeta($section, ['block_title' => 'New block title'], $tenant->id);

        $data = $section->fresh()->data_json;
        $this->assertIsArray($data);
        $this->assertSame('New block title', $data['title']);
    }

    public function test_delete_main_section_throws(): void
    {
        $tenant = $this->tenantWithDomain('pbdel');
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'P',
            'slug' => 'pbdel-page',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $main = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'main',
            'section_type' => 'rich_text',
            'title' => 'Main',
            'data_json' => ['content' => 'x'],
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $this->expectException(RuntimeException::class);
        app(PageSectionOperationsService::class)->deleteSection($main, $tenant->id);
    }

    public function test_section_view_resolver_finds_default_theme_partial(): void
    {
        $tenant = $this->tenantWithDomain('pbview');
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'P',
            'slug' => 'pbview-page',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $section = PageSection::query()->create([
            'tenant_id' => $tenant->id,
            'page_id' => $page->id,
            'section_key' => 'notice_box_1',
            'section_type' => 'notice_box',
            'title' => 'C',
            'data_json' => ['tone' => 'info', 'text' => '<p>x</p>'],
            'sort_order' => 10,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $name = app(SectionViewResolver::class)->resolveViewName($section, $tenant);
        $this->assertNotNull($name);
        $this->assertStringContainsString('sections.notice-box', $name);
    }
}
