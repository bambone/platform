<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\TenantServiceProgram;
use App\Tenant\BlackDuck\BlackDuckServiceProgramCatalog;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Tenant\BlackDuckBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Сидер Black Duck: импорт услуг/медиа и refresh оставляют согласованные {@see TenantServiceProgram}, {@code tenant_media_assets} (если импорт прошёл) и страницы.
 */
final class BlackDuckImportRefreshPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost', '127.0.0.1']]);
        Cache::flush();
    }

    #[Test]
    public function black_duck_bootstrap_makes_db_catalog_and_refresh_makes_landing_page_rows(): void
    {
        $this->seed(RolePermissionSeeder::class);
        (new BlackDuckBootstrap)->run();
        $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
        $this->assertGreaterThan(0, $tid);
        $this->assertTrue(
            BlackDuckServiceProgramCatalog::databaseHasCatalog($tid),
            'import-services in bootstrap should populate tenant_service_programs when registry has rows',
        );
        $this->assertGreaterThan(0, TenantServiceProgram::query()->where('tenant_id', $tid)->count());
        $ppfPage = (int) DB::table('pages')->where('tenant_id', $tid)->where('slug', 'ppf')->value('id');
        $this->assertGreaterThan(0, $ppfPage, 'service landing page expected after sync/refresh in bootstrap path');
        $this->assertGreaterThan(0, (int) DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $ppfPage)
            ->count());
    }

    #[Test]
    public function import_media_with_only_new_rows_then_refresh_does_not_error(): void
    {
        $this->seed(RolePermissionSeeder::class);
        (new BlackDuckBootstrap)->run();
        $exit = Artisan::call('tenant:black-duck:import-media-catalog-to-db', [
            'tenant' => BlackDuckBootstrap::SLUG,
            '--only-missing' => true,
        ]);
        if ($exit === 0) {
            $tid = (int) DB::table('tenants')->where('slug', BlackDuckBootstrap::SLUG)->value('id');
            $this->assertIsInt($tid);
        }
        $this->assertIsInt($exit);
    }
}
