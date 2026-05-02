<?php

namespace Tests\Unit\PageBuilder;

use App\MediaPresentation\PresentationData;
use App\PageBuilder\Blueprints\Expert\ExpertHeroBlueprint;
use PHPUnit\Framework\TestCase;

final class ExpertHeroBlueprintNormalizationTest extends TestCase
{
    public function test_normalize_fills_viewport_focal_map_for_empty_presentation(): void
    {
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor([
            'heading' => 'H',
            'hero_background_presentation' => PresentationData::empty()->toArray(),
        ]);
        $map = $out['hero_background_presentation']['viewport_focal_map'] ?? [];
        $this->assertArrayHasKey('mobile', $map);
        $this->assertArrayHasKey('tablet', $map);
        $this->assertArrayHasKey('desktop', $map);
        foreach (['mobile', 'tablet', 'desktop'] as $k) {
            $this->assertArrayHasKey('x', $map[$k]);
            $this->assertArrayHasKey('y', $map[$k]);
            $this->assertArrayHasKey('scale', $map[$k]);
        }
    }

    public function test_normalize_migrates_legacy_sync_even_when_input_was_premerged_with_defaults(): void
    {
        $defaults = (new ExpertHeroBlueprint)->defaultData();
        $storage = [
            'hero_focal_sync_mobile_desktop' => true,
            'hero_background_presentation' => [
                'version' => 2,
                'viewport_focal_map' => [
                    'mobile' => ['x' => 82, 'y' => 18, 'scale' => 1],
                    'desktop' => ['x' => 76, 'y' => 10, 'scale' => 1],
                ],
            ],
        ];
        $premerged = array_replace_recursive($defaults, $storage);
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor($premerged, $storage);
        $this->assertTrue($out['hero_focal_sync_all_viewports']);
        $this->assertArrayNotHasKey('hero_focal_sync_mobile_desktop', $out);
    }

    public function test_normalize_legacy_missing_tablet_copies_mobile_to_preserve_old_tablet_crop(): void
    {
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor([
            'hero_background_presentation' => [
                'version' => 2,
                'viewport_focal_map' => [
                    'mobile' => ['x' => 83, 'y' => 17, 'scale' => 1.15],
                    'desktop' => ['x' => 70, 'y' => 12, 'scale' => 1],
                ],
            ],
        ]);
        $map = $out['hero_background_presentation']['viewport_focal_map'];
        $this->assertSame($map['mobile']['x'], $map['tablet']['x']);
        $this->assertSame($map['mobile']['y'], $map['tablet']['y']);
        $this->assertSame($map['mobile']['scale'], $map['tablet']['scale']);
    }

    public function test_normalize_without_legacy_mobile_keeps_profile_tablet_default_not_copied(): void
    {
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor([
            'hero_background_presentation' => PresentationData::empty()->toArray(),
        ]);
        $map = $out['hero_background_presentation']['viewport_focal_map'];
        $this->assertSame(82.0, $map['mobile']['x']);
        $this->assertSame(50.0, $map['tablet']['x']);
        $this->assertSame(76.0, $map['desktop']['x']);
    }

    public function test_normalize_storage_new_sync_true_wins_when_legacy_was_false(): void
    {
        $defaults = (new ExpertHeroBlueprint)->defaultData();
        $merged = array_replace_recursive($defaults, [
            'hero_focal_sync_all_viewports' => true,
            'hero_focal_sync_mobile_desktop' => false,
        ]);
        $storage = [
            'hero_focal_sync_all_viewports' => true,
            'hero_focal_sync_mobile_desktop' => false,
        ];
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor($merged, $storage);
        $this->assertTrue($out['hero_focal_sync_all_viewports']);
    }

    public function test_normalize_migrates_legacy_sync_toggle_to_all_viewports(): void
    {
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor([
            'hero_focal_sync_mobile_desktop' => true,
        ]);
        $this->assertTrue($out['hero_focal_sync_all_viewports']);
        $this->assertArrayNotHasKey('hero_focal_sync_mobile_desktop', $out);
    }

    public function test_normalize_prefers_new_sync_field_over_legacy(): void
    {
        $defaults = (new ExpertHeroBlueprint)->defaultData();
        $merged = array_replace_recursive($defaults, [
            'hero_focal_sync_all_viewports' => false,
            'hero_focal_sync_mobile_desktop' => true,
        ]);
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor($merged, [
            'hero_focal_sync_all_viewports' => false,
            'hero_focal_sync_mobile_desktop' => true,
        ]);
        $this->assertFalse($out['hero_focal_sync_all_viewports']);
    }

    public function test_normalize_preserves_sync_all_viewports_from_input_without_storage_snapshot(): void
    {
        $out = ExpertHeroBlueprint::normalizeHeroPresentationForEditor([
            'hero_focal_sync_all_viewports' => true,
        ]);
        $this->assertTrue($out['hero_focal_sync_all_viewports']);
    }
}
