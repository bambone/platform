<?php

namespace Tests\Unit\MediaPresentation;

use App\MediaPresentation\Profiles\PageHeroCoverPresentationProfile;
use App\MediaPresentation\ViewportKey;
use PHPUnit\Framework\TestCase;

final class PageHeroCoverViewportMappingTest extends TestCase
{
    public function test_page_hero_cover_width_maps_to_expected_viewport(): void
    {
        $this->assertSame(ViewportKey::Mobile, PageHeroCoverPresentationProfile::viewportKeyForWidth(390));
        $this->assertSame(ViewportKey::Mobile, PageHeroCoverPresentationProfile::viewportKeyForWidth(767));
        $this->assertSame(ViewportKey::Tablet, PageHeroCoverPresentationProfile::viewportKeyForWidth(768));
        $this->assertSame(ViewportKey::Tablet, PageHeroCoverPresentationProfile::viewportKeyForWidth(1023));
        $this->assertSame(ViewportKey::Desktop, PageHeroCoverPresentationProfile::viewportKeyForWidth(1024));
    }
}
