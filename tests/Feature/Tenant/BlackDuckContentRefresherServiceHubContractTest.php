<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * {@see BlackDuckContentRefresher}: пустой витринный hub при DB-каталоге — {@code is_visible = false} на service_hub.
 */
final class BlackDuckContentRefresherServiceHubContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function refresh_hides_home_service_hub_when_db_catalog_exists_but_no_visible_rows_for_vitrine(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Hub T',
            'slug' => 'bd-hub-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        TenantServiceProgram::query()->create([
            'tenant_id' => $tid,
            'slug' => 'only-hidden',
            'title' => 'H',
            'teaser' => 't',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => false,
            'is_featured' => false,
            'sort_order' => 0,
            'catalog_meta_json' => [
                'has_landing' => true,
                'booking_mode' => 'confirm',
            ],
        ]);
        $home = Page::query()->create([
            'tenant_id' => $tid,
            'name' => 'Главная',
            'slug' => 'home',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        PageSection::query()->create([
            'tenant_id' => $tid,
            'page_id' => (int) $home->id,
            'section_key' => 'service_hub',
            'section_type' => 'service_hub_grid',
            'title' => 'Услуги',
            'data_json' => [
                'heading' => 'Услуги',
                'items' => [],
                'groups' => [],
            ],
            'is_visible' => true,
            'status' => 'published',
            'sort_order' => 20,
        ]);
        $tenant = $tenant->fresh();
        $this->assertNotNull($tenant);
        app(BlackDuckContentRefresher::class)->refreshContent($tenant, [
            'force' => false,
            'if_placeholder' => true,
            'only_seo' => false,
            'force_section' => null,
            'dry_run' => false,
        ]);
        $visible = (bool) DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('section_key', 'service_hub')
            ->where('page_id', (int) $home->id)
            ->value('is_visible');
        $this->assertFalse($visible);
    }
}
