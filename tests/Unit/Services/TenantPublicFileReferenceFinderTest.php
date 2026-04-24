<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Services\CurrentTenantManager;
use App\Services\TenantFiles\TenantPublicFileReferenceFinder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TenantPublicFileReferenceFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_path_in_page_section_data_json(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Ref',
            'slug' => 'trf-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        app(CurrentTenantManager::class)->setTenant($tenant);

        $key = "tenants/{$tid}/public/site/brand/needle-test.jpg";
        $page = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'H',
            'slug' => 'h',
            'template' => 'default',
            'status' => 'published',
        ]);
        $section = PageSection::query()->create([
            'tenant_id' => $tid,
            'page_id' => $page->id,
            'section_key' => 'k',
            'section_type' => 'rich_text',
            'data_json' => [
                'content' => 'Look '.$key.' at',
            ],
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $rawRow = DB::table('page_sections')->where('id', $section->id)->first(['data_json', 'tenant_id']);
        $this->assertNotNull($rawRow);
        $this->assertSame($tid, (int) $rawRow->tenant_id, 'Section tenant_id should match current tenant id');
        $rawJson = (string) $rawRow->data_json;
        $this->assertStringContainsString('needle-test', $rawJson, 'DB row should contain the path string');

        $direct = PageSection::query()
            ->where('tenant_id', $tid)
            ->whereRaw('cast(data_json as text) like ?', ['%'.str_replace('/', '\/', $key).'%'])
            ->count();
        $this->assertSame(1, $direct, 'LIKE on JSON-escaped path should find the row');

        $labels = app(TenantPublicFileReferenceFinder::class)->findReferenceLabels($tid, $key);
        $this->assertNotEmpty($labels, 'Finder should detect object key in page_sections.data_json');
    }

    public function test_finds_reference_when_only_site_relative_path_in_json(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Rel',
            'slug' => 'trf2-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        app(CurrentTenantManager::class)->setTenant($tenant);

        $relOnly = 'site/uploads/page-builder/case-study/only-rel-'.substr(uniqid(), -6).'.webp';
        $fullKey = "tenants/{$tid}/public/{$relOnly}";

        $page = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'P',
            'slug' => 'p',
            'template' => 'default',
            'status' => 'published',
        ]);
        PageSection::query()->create([
            'tenant_id' => $tid,
            'page_id' => $page->id,
            'section_key' => 'k2',
            'section_type' => 'rich_text',
            'data_json' => [
                'image' => $relOnly,
            ],
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $labels = app(TenantPublicFileReferenceFinder::class)->findReferenceLabels($tid, $fullKey);
        $this->assertNotEmpty($labels, 'Finder should match logical site/... path without full object key in JSON');
    }

    public function test_finds_reference_when_public_prefix_in_value(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Pub',
            'slug' => 'trf3-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        app(CurrentTenantManager::class)->setTenant($tenant);

        $rel = 'site/brand/pub-prefix-'.substr(uniqid(), -6).'.png';
        $fullKey = "tenants/{$tid}/public/{$rel}";
        $asPublic = 'public/'.$rel;

        $page = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'P2',
            'slug' => 'p2',
            'template' => 'default',
            'status' => 'published',
        ]);
        PageSection::query()->create([
            'tenant_id' => $tid,
            'page_id' => $page->id,
            'section_key' => 'k3',
            'section_type' => 'rich_text',
            'data_json' => [
                'url' => $asPublic,
            ],
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $labels = app(TenantPublicFileReferenceFinder::class)->findReferenceLabels($tid, $fullKey);
        $this->assertNotEmpty($labels, 'Finder should match public/... form stored in content');
    }

    public function test_finds_reference_when_leading_slash_site_path_in_json(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Lead',
            'slug' => 'trf4-'.substr(uniqid(), -8),
            'theme_key' => 'expert_auto',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        app(CurrentTenantManager::class)->setTenant($tenant);

        $rel = 'site/brand/lead-'.substr(uniqid(), -6).'.svg';
        $fullKey = "tenants/{$tid}/public/{$rel}";

        $page = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'P3',
            'slug' => 'p3',
            'template' => 'default',
            'status' => 'published',
        ]);
        PageSection::query()->create([
            'tenant_id' => $tid,
            'page_id' => $page->id,
            'section_key' => 'k4',
            'section_type' => 'rich_text',
            'data_json' => [
                'src' => '/'.$rel,
            ],
            'sort_order' => 0,
            'is_visible' => true,
            'status' => 'published',
        ]);

        $labels = app(TenantPublicFileReferenceFinder::class)->findReferenceLabels($tid, $fullKey);
        $this->assertNotEmpty($labels, 'Finder should match leading-slash path in JSON');
    }
}
