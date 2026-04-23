<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\Blueprints\AbstractPageSectionBlueprint;

/**
 * Секции визуала Black Duck: доступны только при {@code theme_key = black_duck}.
 */
abstract class BlackDuckSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function supportsTheme(string $themeKey): bool
    {
        return $themeKey === 'black_duck';
    }
}
