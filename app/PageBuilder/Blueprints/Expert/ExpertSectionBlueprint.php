<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\Blueprints\AbstractPageSectionBlueprint;

abstract class ExpertSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function supportsTheme(string $themeKey): bool
    {
        return $themeKey === 'expert_auto';
    }
}
