<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Tenant\BlackDuck\BlackDuckMapsReviewCatalog;
use App\Tenant\BlackDuck\BlackDuckServiceProgramCatalog;
use App\Tenant\BlackDuck\BlackDuckServiceRegistry;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * {@see BlackDuckMapsReviewCatalog::landingSlugOrder($tenantId)} при заполненном {@see BlackDuckServiceProgramCatalog::databaseHasCatalog} — без fallback в PHP-реестр.
 */
final class BlackDuckMapsReviewCatalogDbTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function landing_slug_order_with_db_catalog_is_db_only_not_registry(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Maps',
            'slug' => 'bd-maps-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        TenantServiceProgram::query()->create([
            'tenant_id' => $tid,
            'slug' => 'unique-maps-landing',
            'title' => 'Unique',
            'teaser' => 't',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'catalog_meta_json' => [
                'has_landing' => true,
                'booking_mode' => 'confirm',
            ],
        ]);
        $order = BlackDuckMapsReviewCatalog::landingSlugOrder($tid);
        $this->assertSame(['unique-maps-landing'], $order);
        $fromRegistry = [];
        foreach (BlackDuckServiceRegistry::all() as $r) {
            if ($r['has_landing'] && ! str_starts_with((string) $r['slug'], '#')) {
                $fromRegistry[] = $r['slug'];
            }
        }
        $this->assertNotSame($fromRegistry, $order, 'DB-only order must not mirror full registry for this scenario');
    }

    #[Test]
    public function landing_slug_order_with_db_catalog_and_no_visible_landing_is_empty_array(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Maps Empty',
            'slug' => 'bd-maps-e-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        TenantServiceProgram::query()->create([
            'tenant_id' => $tid,
            'slug' => 'hidden-landing',
            'title' => 'H',
            'teaser' => 't',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'catalog_meta_json' => [
                'has_landing' => false,
                'booking_mode' => 'confirm',
            ],
        ]);
        $this->assertTrue(BlackDuckServiceProgramCatalog::databaseHasCatalog($tid));
        $this->assertSame([], BlackDuckMapsReviewCatalog::landingSlugOrder($tid));
    }
}
