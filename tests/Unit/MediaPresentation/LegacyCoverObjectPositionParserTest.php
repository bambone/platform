<?php

namespace Tests\Unit\MediaPresentation;

use App\MediaPresentation\LegacyCoverObjectPositionParser;
use PHPUnit\Framework\TestCase;

final class LegacyCoverObjectPositionParserTest extends TestCase
{
    public function test_presets_parse(): void
    {
        $p = LegacyCoverObjectPositionParser::parse('center 18%');
        $this->assertNotNull($p);
        $this->assertSame(50.0, $p->x);
        $this->assertSame(18.0, $p->y);

        $this->assertNull(LegacyCoverObjectPositionParser::parse(''));
        $this->assertNull(LegacyCoverObjectPositionParser::parse('auto'));
    }

    public function test_unknown_returns_null(): void
    {
        $this->assertNull(LegacyCoverObjectPositionParser::parse('left 20% top 30%'));
        $this->assertNull(LegacyCoverObjectPositionParser::parse('calc(10%)'));
    }

    public function test_center_percent_accepts_decimal_fraction(): void
    {
        $p = LegacyCoverObjectPositionParser::parse('center 18.25%');
        $this->assertNotNull($p);
        $this->assertSame(18.3, $p->y);
    }
}
