<?php

namespace Tests\Unit\MediaPresentation;

use App\MediaPresentation\FramingPresentationSummaryResolver;
use App\MediaPresentation\MediaPresentationRegistry;
use App\MediaPresentation\PresentationData;
use App\MediaPresentation\Profiles\PageHeroCoverPresentationProfile;
use App\MediaPresentation\Profiles\PageHeroCoverSlotProfile;
use Tests\TestCase;

class FramingPresentationSummaryResolverTest extends TestCase
{
    public function test_empty_presentation_is_default_label(): void
    {
        $r = app(FramingPresentationSummaryResolver::class);
        $profile = MediaPresentationRegistry::profile(PageHeroCoverPresentationProfile::SLOT_ID);
        $this->assertInstanceOf(PageHeroCoverSlotProfile::class, $profile);
        $out = $r->summarize(PresentationData::empty()->toArray(), $profile);

        $this->assertStringContainsString('умолчан', $out['label']);
    }

    public function test_tablet_only_change_is_not_default(): void
    {
        $r = app(FramingPresentationSummaryResolver::class);
        $profile = MediaPresentationRegistry::profile(PageHeroCoverPresentationProfile::SLOT_ID);
        $row = [
            'version' => 2,
            'viewport_focal_map' => [
                'mobile' => ['x' => 82, 'y' => 18, 'scale' => 1],
                'tablet' => ['x' => 40, 'y' => 50, 'scale' => 1],
                'desktop' => ['x' => 76, 'y' => 10, 'scale' => 1],
            ],
        ];
        $out = $r->summarize($row, $profile);
        $this->assertStringContainsString('настроено', $out['label']);
    }
}
