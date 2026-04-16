<?php

namespace App\MediaPresentation;

use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;

/**
 * Resolves presentation profile constants by slot id (platform extension point).
 */
final class MediaPresentationRegistry
{
    public static function defaultFocalForSlot(string $slotId, ViewportKey $key): FocalPoint
    {
        return match ($slotId) {
            ServiceProgramCardPresentationProfile::SLOT_ID => ServiceProgramCardPresentationProfile::defaultFocalForViewport($key),
            default => FocalPoint::center(),
        };
    }

    /**
     * Future slots (hero, builder, …) register here — same resolver contract as {@see ServiceProgramCardPresentationProfile}.
     *
     * @return list<string>
     */
    public static function registeredSlotIds(): array
    {
        return [
            ServiceProgramCardPresentationProfile::SLOT_ID,
        ];
    }

    public static function slotExists(string $slotId): bool
    {
        return in_array($slotId, self::registeredSlotIds(), true);
    }
}
