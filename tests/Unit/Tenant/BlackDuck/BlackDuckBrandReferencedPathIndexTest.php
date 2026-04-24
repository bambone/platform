<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\BlackDuck;

use App\Tenant\BlackDuck\BlackDuckBrandReferencedPathIndex;
use PHPUnit\Framework\TestCase;

final class BlackDuckBrandReferencedPathIndexTest extends TestCase
{
    public function test_extracts_site_brand_paths(): void
    {
        $text = 'x "site/brand/proof/a.png" y brand/proof/b.webp z';
        $paths = BlackDuckBrandReferencedPathIndex::extractBrandPathsFromText($text);
        sort($paths);
        self::assertSame(['site/brand/proof/a.png', 'site/brand/proof/b.webp'], $paths);
    }

    public function test_normalize_to_logical_key(): void
    {
        self::assertSame(
            'site/brand/hero.webp',
            BlackDuckBrandReferencedPathIndex::normalizeToLogicalKey('brand/hero.webp'),
        );
        self::assertNull(BlackDuckBrandReferencedPathIndex::normalizeToLogicalKey(''));
    }
}
