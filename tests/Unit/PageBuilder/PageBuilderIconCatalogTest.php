<?php

namespace Tests\Unit\PageBuilder;

use App\PageBuilder\PageBuilderIconCatalog;
use Closure;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageBuilderIconCatalogTest extends TestCase
{
    #[Test]
    public function info_cards_subset_is_allowed_in_group(): void
    {
        $this->assertTrue(PageBuilderIconCatalog::isAllowedKey('check', 'info_cards'));
        $this->assertFalse(PageBuilderIconCatalog::isAllowedKey('coast', 'info_cards'));
    }

    #[Test]
    public function coast_in_features_group(): void
    {
        $this->assertTrue(PageBuilderIconCatalog::isAllowedKey('coast', 'features'));
        $this->assertNotNull(PageBuilderIconCatalog::heroiconForKey('coast'));
    }

    #[Test]
    public function strict_validation_rejects_unknown_key(): void
    {
        $v = Validator::make(
            ['icon' => 'not-in-catalog'],
            ['icon' => [
                function (string $attribute, mixed $value, Closure $fail): void {
                    PageBuilderIconCatalog::validateIconValue($value, 'features', false, $fail);
                },
            ]],
        );
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function legacy_mode_accepts_slug_not_in_whitelist(): void
    {
        $v = Validator::make(
            ['icon' => 'custom_icon-1'],
            ['icon' => [
                function (string $attribute, mixed $value, Closure $fail): void {
                    PageBuilderIconCatalog::validateIconValue($value, 'features', true, $fail);
                },
            ]],
        );
        $this->assertFalse($v->fails());
    }
}
