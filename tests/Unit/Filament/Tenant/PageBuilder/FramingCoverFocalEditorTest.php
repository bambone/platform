<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Tenant\PageBuilder;

use App\Filament\Tenant\PageBuilder\FramingCoverFocalEditor;
use App\MediaPresentation\Profiles\PageHeroCoverPresentationProfile;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use PHPUnit\Framework\TestCase;

final class FramingCoverFocalEditorTest extends TestCase
{
    public function test_height_fit_only_for_page_hero_cover_slot(): void
    {
        $this->assertSame(
            'height_fit',
            FramingCoverFocalEditor::focalPreviewFitForSlotId(PageHeroCoverPresentationProfile::SLOT_ID)
        );
        $this->assertSame(
            'cover',
            FramingCoverFocalEditor::focalPreviewFitForSlotId(ServiceProgramCardPresentationProfile::SLOT_ID)
        );
    }
}
