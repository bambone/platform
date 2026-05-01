<?php

namespace App\MediaPresentation\Profiles;

use App\MediaPresentation\Contracts\SlotPresentationProfileInterface;
use App\MediaPresentation\FocalPoint;
use App\MediaPresentation\ViewportKey;

/**
 * Adapter {@see SlotPresentationProfileInterface} для слота page_hero_cover: instance-методы делегируют в
 * {@see PageHeroCoverPresentationProfile} (static). Регистрация: {@see MediaPresentationRegistry}.
 */
final class PageHeroCoverSlotProfile implements SlotPresentationProfileInterface
{
    public function slotId(): string
    {
        return PageHeroCoverPresentationProfile::SLOT_ID;
    }

    public function defaultFocalForViewport(ViewportKey $key): FocalPoint
    {
        return PageHeroCoverPresentationProfile::defaultFocalForViewport($key);
    }

    public function previewFrames(): array
    {
        return PageHeroCoverPresentationProfile::previewFrames();
    }

    public function viewportKeyForWidth(int $widthPx): ViewportKey
    {
        return PageHeroCoverPresentationProfile::viewportKeyForWidth($widthPx);
    }

    public function framingScaleMin(): float
    {
        return PageHeroCoverPresentationProfile::FRAMING_SCALE_MIN;
    }

    public function framingScaleMax(): float
    {
        return PageHeroCoverPresentationProfile::FRAMING_SCALE_MAX;
    }

    public function framingScaleStep(): float
    {
        return PageHeroCoverPresentationProfile::FRAMING_SCALE_STEP;
    }

    public function framingScaleDefault(): float
    {
        return PageHeroCoverPresentationProfile::FRAMING_SCALE_DEFAULT;
    }

    public function safeAreaBottomBand(): array
    {
        return PageHeroCoverPresentationProfile::safeAreaBottomBand();
    }

    public function articleOverlayCssVariables(): array
    {
        return PageHeroCoverPresentationProfile::articleOverlayCssVariables();
    }
}
