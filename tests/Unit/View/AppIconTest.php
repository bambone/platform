<?php

namespace Tests\Unit\View;

use App\View\Components\AppIcon;
use Tests\TestCase;

final class AppIconTest extends TestCase
{
    public function test_known_name_returns_non_empty_markup(): void
    {
        $c = new AppIcon('telegram', 'h-6 w-6', true);
        $html = $c->svgMarkup();
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('class="h-6 w-6"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_unknown_name_returns_empty_string(): void
    {
        $c = new AppIcon('definitely-not-in-allowlist', 'h-6 w-6', true);
        $this->assertSame('', $c->svgMarkup());
    }
}
