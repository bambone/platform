<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\TenantPublicSiteTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantPublicSiteThemeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_default_theme_rows(): void
    {
        $this->assertTrue(TenantPublicSiteTheme::query()->where('theme_key', 'expert_pr')->exists());
        $this->assertTrue(TenantPublicSiteTheme::query()->where('theme_key', 'moto')->where('is_active', true)->exists());
    }

    public function test_options_include_active_themes_and_inactive_only_for_current(): void
    {
        TenantPublicSiteTheme::query()->where('theme_key', 'moto')->update(['is_active' => false]);

        $withoutCurrent = TenantPublicSiteTheme::optionsForTenantForm();
        $this->assertArrayNotHasKey('moto', $withoutCurrent);

        $withCurrent = TenantPublicSiteTheme::optionsForTenantForm('moto');
        $this->assertArrayHasKey('moto', $withCurrent);
    }
}
