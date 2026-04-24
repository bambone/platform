<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Models\Tenant;
use App\Models\TenantMediaAsset;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use App\Tenant\BlackDuck\BlackDuckMediaRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Каталог медиа: при существующей `tenant_media_assets` пустой таблицы = пустой каталог, без чтения JSON.
 */
final class BlackDuckMediaCatalogDbFirstTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function load_or_empty_does_not_resurrect_file_when_table_exists_but_has_zero_rows(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $disk = TenantStorageDisks::publicDiskName();
        $tenant = Tenant::query()->create([
            'name' => 'M DB First',
            'slug' => 'm-db-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $ts = TenantStorage::forTrusted($tid);
        $rawPath = $ts->publicPath(BlackDuckMediaCatalog::CATALOG_LOGICAL);
        $legacyJson = json_encode([
            'version' => 3,
            'assets' => [
                [
                    'role' => BlackDuckMediaRole::WorksGallery->value,
                    'service_slug' => 'ppf',
                    'logical_path' => 'site/brand/proof/legacy-only.jpg',
                    'sort_order' => 0,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        Storage::disk($disk)->put($rawPath, $legacyJson);
        $this->assertNotSame('', (string) Storage::disk($disk)->get($rawPath));

        $this->assertTrue(BlackDuckMediaCatalog::isCatalogSourcedFromDatabaseForLoadPath());
        $loaded = BlackDuckMediaCatalog::loadOrEmpty($tid);
        $this->assertSame([], $loaded['assets'], 'таблица есть, 0 строк, JSON в storage — публичный путь loadOrEmpty всё равно из БД, без fallback в файл');
    }

    #[Test]
    public function import_from_json_populates_works_portfolio_grid(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tenant = Tenant::query()->create([
            'name' => 'Grid DB',
            'slug' => 'grid-db-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        $ts = TenantStorage::forTrusted($tid);
        $this->assertTrue($ts->putPublic('site/brand/proof/w-grid.jpg', 'x', [
            'ContentType' => 'image/jpeg',
            'visibility' => 'public',
        ]));
        Log::spy();
        $outcome = BlackDuckMediaCatalog::saveCatalogWithOutcome($tid, BlackDuckMediaCatalog::SCHEMA_VERSION, [
            [
                'role' => BlackDuckMediaRole::WorksGallery->value,
                'service_slug' => 'ppf',
                'logical_path' => 'site/brand/proof/w-grid.jpg',
                'sort_order' => 0,
                'caption' => 'c',
            ],
        ]);
        $this->assertTrue($outcome['wrote_to_disk']);
        $this->assertTrue($outcome['public_site_reads_database'], 'в тесте таблица tenant_media_assets есть');
        $this->assertFalse($outcome['public_site_will_see_these_changes'], 'JSON на диске не равно тому, что читает публичный каталог (БД)');
        Log::shouldHaveReceived('warning')->withArgs(
            function (string $m, array $c = []): bool {
                return str_contains($m, 'saveCatalog wrote media-catalog.json only')
                    && (int) ($c['tenant_id'] ?? 0) > 0;
            },
        );
        $exit = Artisan::call('tenant:black-duck:import-media-catalog-to-db', [
            'tenant' => (string) $tenant->slug,
            '--wipe' => true,
        ]);
        $this->assertSame(0, $exit, Artisan::output());
        $this->assertGreaterThan(
            0,
            TenantMediaAsset::query()->where('tenant_id', $tid)->count(),
            'строки tenant_media_assets после импорта: '.Artisan::output(),
        );
        $loaded = BlackDuckMediaCatalog::loadOrEmpty($tid);
        $this->assertNotSame([], $loaded['assets'] ?? [], json_encode($loaded, JSON_UNESCAPED_UNICODE));
        $p = (string) ($loaded['assets'][0]['logical_path'] ?? '');
        $this->assertTrue(
            BlackDuckMediaCatalog::logicalPathIsUsable($tid, $p),
            'usable: '.$p,
        );
        $grid = BlackDuckMediaCatalog::worksPortfolioGridItems($tid);
        $this->assertNotSame([], $grid, 'после импорта в БД сетка /raboty не пуста');
    }
}
