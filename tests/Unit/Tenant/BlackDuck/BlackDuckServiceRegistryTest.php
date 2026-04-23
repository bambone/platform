<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckServiceRegistry;
use Tests\TestCase;

final class BlackDuckServiceRegistryTest extends TestCase
{
    public function test_legacy_matrix_matches_content_constants_contract(): void
    {
        $legacy = BlackDuckServiceRegistry::legacyMatrixQ1();
        $fromConstants = BlackDuckContentConstants::serviceMatrixQ1();
        $this->assertSame($legacy, $fromConstants);
    }

    public function test_catalog_has_at_least_five_groups(): void
    {
        $groups = BlackDuckServiceRegistry::catalogGroupsWithPlaceholderItems();
        $this->assertGreaterThanOrEqual(5, count($groups));
    }

    public function test_min_works_portfolio_acceptance_constant(): void
    {
        $this->assertSame(12, BlackDuckServiceRegistry::MIN_WORKS_PORTFOLIO_ITEMS_ACCEPTANCE);
    }
}
