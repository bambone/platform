<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use App\Tenant\BlackDuck\BlackDuckServiceImages;
use App\Tenant\BlackDuck\BlackDuckServiceProgramCatalog;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BlackDuckServiceProgramCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_hub_card_image_falls_back_when_cover_empty(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Cover Img Test',
            'slug' => 'bd-cov-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => 'svc-x',
            'title' => 'Svc',
            'teaser' => 't',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'cover_image_ref' => '',
            'catalog_meta_json' => [
                'has_landing' => true,
                'booking_mode' => 'confirm',
            ],
        ]);
        $tid = (int) $tenant->id;
        $fromCatalog = BlackDuckServiceProgramCatalog::publicServiceHubCardImageLogicalPath($tid, 'svc-x');
        $legacy = BlackDuckServiceImages::firstServiceHubCardPublicPath($tid, 'svc-x');
        $this->assertSame($legacy, $fromCatalog);
    }

    #[Test]
    public function primary_cta_goes_to_inquiry_when_no_landing(): void
    {
        $u = BlackDuckServiceProgramCatalog::primaryCardCtaUrl('ppf', false);
        $this->assertStringContainsString('contacts', $u);
        $this->assertStringContainsString('service=ppf', $u);
    }

    #[Test]
    public function primary_cta_goes_to_path_when_has_landing(): void
    {
        $u = BlackDuckServiceProgramCatalog::primaryCardCtaUrl('ppf', true);
        $this->assertSame('/ppf', $u);
    }

    #[Test]
    public function instant_booking_mode_sets_online_not_confirm(): void
    {
        list($o, $n) = BlackDuckServiceProgramCatalog::bookingUIFromMode('instant');
        $this->assertTrue($o);
        $this->assertFalse($n);
    }

    #[Test]
    public function database_catalog_exists_even_when_all_rows_hidden_does_not_use_empty_visible_as_no_catalog(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Cat Test',
            'slug' => 'bd-cat-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => 'test-svc',
            'title' => 'Test',
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
        $this->assertTrue(BlackDuckServiceProgramCatalog::databaseHasCatalog((int) $tenant->id));
        $this->assertFalse(BlackDuckServiceProgramCatalog::hasVisibleCatalogPrograms((int) $tenant->id));
        $this->assertSame([], BlackDuckServiceProgramCatalog::legacyMatrixQ1ForTenant((int) $tenant->id));
    }

    #[Test]
    public function public_price_anchor_takes_priority_over_formatted_price_amount(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Price Test 1',
            'slug' => 'bd-p1-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => 'ppf',
            'title' => 'PPF',
            'teaser' => 't',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'price_amount' => 1_000_00,
            'price_prefix' => 'от',
            'catalog_meta_json' => [
                'public_price_anchor' => 'от 99 999 ₽ (акция)',
            ],
        ]);
        $this->assertSame(
            'от 99 999 ₽ (акция)',
            BlackDuckServiceProgramCatalog::publicPriceAnchorForSlug((int) $tenant->id, 'ppf')
        );
    }

    #[Test]
    public function when_anchor_empty_uses_formatted_price_amount_with_prefix(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Price Test 2',
            'slug' => 'bd-p2-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => 'tonirovka',
            'title' => 'Тонировка',
            'teaser' => 't',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'price_amount' => 3_000_00,
            'price_prefix' => 'от',
            'catalog_meta_json' => [
                'public_price_anchor' => '',
            ],
        ]);
        $out = BlackDuckServiceProgramCatalog::publicPriceAnchorForSlug((int) $tenant->id, 'tonirovka');
        $this->assertIsString($out);
        $this->assertStringStartsWith('от ', $out);
    }

    #[Test]
    public function when_no_anchor_and_no_price_amount_falls_back_to_registry_or_null(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Price Test 3',
            'slug' => 'bd-p3-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => 'tonirovka',
            'title' => 'Тонировка',
            'teaser' => 't',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'price_amount' => null,
            'price_prefix' => 'от',
            'catalog_meta_json' => [
                'public_price_anchor' => ' ',
            ],
        ]);
        $this->assertNull(BlackDuckServiceProgramCatalog::publicPriceAnchorForSlug((int) $tenant->id, 'tonirovka'));
    }

    #[Test]
    public function service_proof_target_slugs_include_all_visible_landings_in_db_mode(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD Proof Slugs',
            'slug' => 'bd-spf-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $tid = (int) $tenant->id;
        TenantServiceProgram::query()->create([
            'tenant_id' => $tid,
            'slug' => 'custom-landing-xyz',
            'title' => 'Custom',
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
        $slugs = BlackDuckServiceProgramCatalog::serviceProofTargetLandingSlugs($tid);
        $this->assertContains('custom-landing-xyz', $slugs);
    }

    #[Test]
    public function service_proof_target_slugs_without_db_catalog_match_legacy_list(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'BD No Catalog',
            'slug' => 'bd-nc-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $this->assertFalse(BlackDuckServiceProgramCatalog::databaseHasCatalog((int) $tenant->id));
        $this->assertSame(
            BlackDuckMediaCatalog::defaultServiceProofSlugsForLegacy(),
            BlackDuckServiceProgramCatalog::serviceProofTargetLandingSlugs((int) $tenant->id),
        );
    }
}
