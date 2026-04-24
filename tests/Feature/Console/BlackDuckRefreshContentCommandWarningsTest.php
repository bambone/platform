<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Tenant\BlackDuck\BlackDuckTenantRuntimeHealth;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BlackDuckRefreshContentCommandWarningsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function refresh_content_prints_operator_warnings_for_empty_db_catalog_and_degraded_service_catalog(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD warn',
            'slug' => 'bd-w-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;

        $this->assertTrue(BlackDuckTenantRuntimeHealth::isMediaRuntimeEmptyInDatabase($tid), 'предпосылка: таблица есть, строк нет');
        $this->assertTrue(BlackDuckTenantRuntimeHealth::isServiceCatalogDegradedForInquiryForm($tid), 'предпосылка: нет видимых программ');

        $exit = Artisan::call('tenant:black-duck:refresh-content', [
            'tenant' => (string) $tid,
        ]);
        $this->assertSame(0, $exit);
        $out = Artisan::output();
        $this->assertStringContainsString('ВНИМАНИЕ: tenant_media_assets пуста', $out);
        $this->assertStringContainsString('import-media-catalog-to-db', $out);
        $this->assertStringContainsString('ВНИМАНИЕ: нет видимых программ/услуг', $out);
    }
}
