<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see BlackDuckMediaCatalog::isCatalogSourcedFromDatabaseForLoadPath() — общий с {@see BlackDuckMediaCatalog::loadOrEmpty}
 */
final class BlackDuckMediaCatalogLoadPathTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function is_catalog_sourced_from_database_is_true_when_tenant_media_assets_migrated(): void
    {
        $this->assertTrue(
            Schema::hasTable('tenant_media_assets'),
            'тесты миграций должны поднимать tenant_media_assets',
        );
        $this->assertTrue(BlackDuckMediaCatalog::isCatalogSourcedFromDatabaseForLoadPath());
    }
}
