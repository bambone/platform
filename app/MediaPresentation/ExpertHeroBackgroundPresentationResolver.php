<?php

namespace App\MediaPresentation;

use App\MediaPresentation\Profiles\PageHeroCoverPresentationProfile;

/**
 * Public + admin: hero background focal + user scale as CSS variables (Expert themes).
 *
 * {@code profile_id} is not stored in section JSON — always {@see PageHeroCoverPresentationProfile}.
 */
final class ExpertHeroBackgroundPresentationResolver
{
    /**
     * Inline style fragment for the {@code <section>} (focal, user scale, base display scale, preview overlays).
     */
    public function sectionStyleAttribute(array $sectionData): string
    {
        $presentation = PresentationData::fromArray(
            $sectionData['hero_background_presentation'] ?? null,
            PageHeroCoverPresentationProfile::FRAMING_SCALE_MIN,
            PageHeroCoverPresentationProfile::FRAMING_SCALE_MAX,
            PageHeroCoverPresentationProfile::FRAMING_SCALE_STEP,
        );
        $map = $presentation->viewportFocalMap;
        $slotId = PageHeroCoverPresentationProfile::SLOT_ID;

        $mobile = $this->resolveFraming($map, ViewportKey::Mobile, $slotId);
        $tablet = $this->resolveFraming($map, ViewportKey::Tablet, $slotId);
        $desktop = $this->resolveFraming($map, ViewportKey::Desktop, $slotId);

        $parts = [
            '--expert-hero-base-mobile: '.PageHeroCoverPresentationProfile::BASE_DISPLAY_SCALE_MOBILE,
            '--expert-hero-base-desktop: '.PageHeroCoverPresentationProfile::BASE_DISPLAY_SCALE_DESKTOP,
            '--expert-hero-focal-x-mobile: '.$mobile['focal']->x.'%',
            '--expert-hero-focal-y-mobile: '.$mobile['focal']->y.'%',
            '--expert-hero-focal-x-tablet: '.$tablet['focal']->x.'%',
            '--expert-hero-focal-y-tablet: '.$tablet['focal']->y.'%',
            '--expert-hero-focal-x-desktop: '.$desktop['focal']->x.'%',
            '--expert-hero-focal-y-desktop: '.$desktop['focal']->y.'%',
            '--expert-hero-scale-mobile: '.$mobile['scale'],
            '--expert-hero-scale-tablet: '.$tablet['scale'],
            '--expert-hero-scale-desktop: '.$desktop['scale'],
        ];
        foreach (PageHeroCoverPresentationProfile::articleOverlayCssVariables() as $name => $value) {
            $parts[] = '--'.$name.': '.$value;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, array<string, mixed>>  $map
     * @return array{focal: FocalPoint, scale: float}
     */
    private function resolveFraming(array $map, ViewportKey $viewport, string $slotId): array
    {
        $framing = FocalMapViewport::pickFramingFromMap(
            $map,
            $viewport,
            PageHeroCoverPresentationProfile::FRAMING_SCALE_MIN,
            PageHeroCoverPresentationProfile::FRAMING_SCALE_MAX,
            PageHeroCoverPresentationProfile::FRAMING_SCALE_STEP,
        );
        if ($framing !== null) {
            return [
                'focal' => $framing->toFocalPoint(),
                'scale' => $framing->scale,
            ];
        }

        $focal = MediaPresentationRegistry::defaultFocalForSlot($slotId, $viewport);

        return [
            'focal' => $focal,
            'scale' => PageHeroCoverPresentationProfile::FRAMING_SCALE_DEFAULT,
        ];
    }
}
