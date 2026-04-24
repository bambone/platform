<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Tenant\BlackDuck\BlackDuckMapsReviewCatalog;
use App\Tenant\BlackDuck\BlackDuckServiceRegistry;
use Tests\TestCase;

final class BlackDuckMapsReviewCatalogTest extends TestCase
{
    public function test_pool_size_matches_landing_slugs_times_distribution(): void
    {
        $pool = BlackDuckMapsReviewCatalog::pool();
        $slugs = BlackDuckMapsReviewCatalog::landingSlugOrder();
        $this->assertNotEmpty($slugs);
        $this->assertNotEmpty($pool);
        $n = count($slugs);
        $pn = count($pool);
        $counts = array_fill(0, $n, intdiv($pn, $n));
        $rem = $pn % $n;
        for ($i = 0; $i < $rem; $i++) {
            $counts[$i]++;
        }
        if (in_array(0, $counts, true) && $pn > 0) {
            foreach ($counts as $i => $c) {
                if ($c === 0) {
                    $counts[$i] = 1;
                }
            }
        }
        $expected = array_sum($counts);
        $rows = BlackDuckMapsReviewCatalog::rowsForDatabaseSeed();
        $this->assertCount($expected, $rows);
        foreach ($slugs as $ix => $slug) {
            $c = 0;
            foreach ($rows as $r) {
                if (($r['category_key'] ?? '') === $slug) {
                    $c++;
                }
            }
            $this->assertSame($counts[$ix], $c, 'slug '.$slug);
        }
    }

    public function test_landing_slug_order_matches_registry_landings(): void
    {
        $fromCatalog = BlackDuckMapsReviewCatalog::landingSlugOrder();
        $fromRegistry = [];
        foreach (BlackDuckServiceRegistry::all() as $r) {
            if ($r['has_landing'] && ! str_starts_with((string) $r['slug'], '#')) {
                $fromRegistry[] = $r['slug'];
            }
        }
        $this->assertSame($fromRegistry, $fromCatalog);
    }
}
