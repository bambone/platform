<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use App\Tenant\BlackDuck\BlackDuckMediaRole;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BlackDuckMediaCatalogTest extends TestCase
{
    public function test_optional_bool_from_row_value_parses_strings_defensively(): void
    {
        $this->assertNull(BlackDuckMediaCatalog::optionalBoolFromRowValue(null));
        $this->assertTrue(BlackDuckMediaCatalog::optionalBoolFromRowValue(true));
        $this->assertFalse(BlackDuckMediaCatalog::optionalBoolFromRowValue(false));
        $this->assertFalse(BlackDuckMediaCatalog::optionalBoolFromRowValue('false'));
        $this->assertFalse(BlackDuckMediaCatalog::optionalBoolFromRowValue('0'));
        $this->assertTrue(BlackDuckMediaCatalog::optionalBoolFromRowValue('true'));
        $this->assertTrue(BlackDuckMediaCatalog::optionalBoolFromRowValue('1'));
        $this->assertNull(BlackDuckMediaCatalog::optionalBoolFromRowValue('not-a-bool'));
    }

    public function test_normalize_asset_row_v2_shape_gets_v3_defaults(): void
    {
        $raw = [
            'role' => BlackDuckMediaRole::WorksGallery->value,
            'logical_path' => 'site/brand/proof/x.jpg',
            'sort_order' => 5,
            'caption' => 'Only caption',
        ];
        $row = BlackDuckMediaCatalog::normalizeAssetRow($raw, null);
        $this->assertNotNull($row);
        $this->assertSame('', $row['title']);
        $this->assertSame([], $row['tags']);
        $this->assertNull($row['show_on_works']);
        $this->assertSame(5, $row['sort_order']);
    }

    public function test_normalize_asset_row_v3_fields_and_derivatives_without_file_validation(): void
    {
        $raw = [
            'role' => BlackDuckMediaRole::ServiceGallery->value,
            'service_slug' => 'ppf',
            'logical_path' => 'site/brand/proof/a.jpg',
            'title' => 'Заголовок',
            'summary' => 'Описание',
            'tags' => [' PPF ', ''],
            'show_on_service' => true,
            'show_on_works' => false,
            'aspect_hint' => '16 / 9',
            'badge' => 'Новое',
            'derivatives' => [
                ['w' => 0, 'logical_path' => 'bad.jpg'],
                ['w' => 800, 'logical_path' => 'site/brand/proof/a-800.webp'],
            ],
        ];
        $row = BlackDuckMediaCatalog::normalizeAssetRow($raw, null);
        $this->assertNotNull($row);
        $this->assertSame('Заголовок', $row['title']);
        $this->assertSame('Описание', $row['summary']);
        $this->assertSame(['PPF'], $row['tags']);
        $this->assertTrue($row['show_on_service']);
        $this->assertFalse($row['show_on_works']);
        $this->assertSame('16 / 9', $row['aspect_hint']);
        $this->assertSame('Новое', $row['badge']);
        $this->assertCount(1, $row['derivatives']);
        $this->assertSame(800, $row['derivatives'][0]['w']);
    }

    public function test_works_portfolio_filter_chips_orders_services_by_matrix_then_tags_sorted(): void
    {
        Storage::fake(TenantStorageDisks::publicDiskName());
        $tid = 42;
        $disk = TenantStorageDisks::publicDiskName();
        $ts = TenantStorage::forTrusted($tid);

        foreach (['site/brand/proof/a.jpg', 'site/brand/proof/b.jpg'] as $logical) {
            Storage::disk($disk)->put($ts->publicPath($logical), '1');
        }

        $catalog = [
            'version' => 3,
            'assets' => [
                [
                    'role' => BlackDuckMediaRole::WorksGallery->value,
                    'service_slug' => 'ppf',
                    'logical_path' => 'site/brand/proof/a.jpg',
                    'sort_order' => 0,
                ],
                [
                    'role' => BlackDuckMediaRole::WorksGallery->value,
                    'service_slug' => 'keramika',
                    'logical_path' => 'site/brand/proof/b.jpg',
                    'sort_order' => 0,
                    'tags' => ['zzz', 'aaa'],
                ],
            ],
        ];
        Storage::disk($disk)->put(
            $ts->publicPath(BlackDuckMediaCatalog::CATALOG_LOGICAL),
            json_encode($catalog, JSON_UNESCAPED_UNICODE),
        );

        $chips = BlackDuckMediaCatalog::worksPortfolioFilterChips($tid);
        $values = array_map(static fn (array $c): string => (string) ($c['value'] ?? ''), $chips);
        $this->assertSame(['all', 'service:ppf', 'service:keramika', 'tag:aaa', 'tag:zzz'], $values);
    }
}
