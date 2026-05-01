<?php

namespace Tests\Unit\MediaPresentation;

use App\MediaPresentation\Contracts\SlotPresentationProfileInterface;
use App\MediaPresentation\MediaPresentationRegistry;
use App\MediaPresentation\Profiles\PageHeroCoverPresentationProfile;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\MediaPresentation\ViewportKey;
use Tests\TestCase;

class MediaPresentationRegistryTest extends TestCase
{
    public function test_profile_returns_service_program_card(): void
    {
        $p = MediaPresentationRegistry::profile(ServiceProgramCardPresentationProfile::SLOT_ID);

        $this->assertSame(ServiceProgramCardPresentationProfile::SLOT_ID, $p->slotId());
    }

    public function test_default_focal_for_unknown_slot_is_center(): void
    {
        $f = MediaPresentationRegistry::defaultFocalForSlot('definitely_unknown_slot_xyz', ViewportKey::Mobile);

        $this->assertSame(50.0, $f->x);
        $this->assertSame(50.0, $f->y);
    }

    public function test_page_hero_cover_registered(): void
    {
        $this->assertTrue(MediaPresentationRegistry::slotExists(PageHeroCoverPresentationProfile::SLOT_ID));
        $p = MediaPresentationRegistry::profile(PageHeroCoverPresentationProfile::SLOT_ID);
        $this->assertInstanceOf(SlotPresentationProfileInterface::class, $p);
        $this->assertSame(PageHeroCoverPresentationProfile::SLOT_ID, $p->slotId());
    }
}
