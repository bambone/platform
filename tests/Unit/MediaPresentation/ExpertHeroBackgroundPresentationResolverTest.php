<?php

namespace Tests\Unit\MediaPresentation;

use App\MediaPresentation\ExpertHeroBackgroundPresentationResolver;
use Tests\TestCase;

final class ExpertHeroBackgroundPresentationResolverTest extends TestCase
{
    public function test_section_style_emits_tablet_css_variables(): void
    {
        $s = (new ExpertHeroBackgroundPresentationResolver)->sectionStyleAttribute([
            'hero_background_presentation' => [
                'version' => 2,
                'viewport_focal_map' => [
                    'mobile' => ['x' => 80, 'y' => 20, 'scale' => 1],
                    'tablet' => ['x' => 33, 'y' => 44, 'scale' => 1.1],
                    'desktop' => ['x' => 76, 'y' => 10, 'scale' => 1],
                ],
            ],
        ]);

        $this->assertStringContainsString('--expert-hero-focal-x-tablet: 33%', $s);
        $this->assertStringContainsString('--expert-hero-focal-y-tablet: 44%', $s);
        $this->assertStringContainsString('--expert-hero-scale-tablet: 1.1', $s);
    }
}
