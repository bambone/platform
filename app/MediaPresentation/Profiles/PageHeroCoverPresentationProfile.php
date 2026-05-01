<?php

namespace App\MediaPresentation\Profiles;

use App\MediaPresentation\FocalPoint;
use App\MediaPresentation\ViewportKey;

/**
 * Slot: {@code page_hero_cover} — Expert hero full-bleed background (expert_auto / advocate_editorial).
 *
 * **Source semantics (MVP):** one URL {@code hero_image_url} (or legacy {@code hero_image_slot.url}) for all widths.
 * Focal point and zoom may differ per viewport via {@code PresentationData::viewport_focal_map}; separate image files per breakpoint are out of scope.
 *
 * Default focal points mirror the pre–CSS-variable hero crop (expert_auto).
 */
final class PageHeroCoverPresentationProfile
{
    public const SLOT_ID = 'page_hero_cover';

    public const PICTURE_MOBILE_MAX_PX = 1023;

    /** User zoom: below 1 zooms out (hero: fit portrait height in block); program cards keep min 1 in their profile. */
    public const FRAMING_SCALE_MIN = 0.5;

    /** Достаточно для «вытянуть» portrait+жёлтый фон почти на всю ширину desktop при height-fit. */
    public const FRAMING_SCALE_MAX = 3.0;

    public const FRAMING_SCALE_STEP = 0.05;

    public const FRAMING_SCALE_DEFAULT = 1.0;

    /** Base display zoom baked into CSS (multiplied with user framing scale). */
    public const BASE_DISPLAY_SCALE_MOBILE = 1.02;

    public const BASE_DISPLAY_SCALE_DESKTOP = 1.03;

    public static function defaultFocalForViewport(ViewportKey $key): FocalPoint
    {
        return match ($key) {
            ViewportKey::Mobile => FocalPoint::normalized(82.0, 18.0),
            ViewportKey::Tablet => FocalPoint::normalized(50.0, 50.0),
            ViewportKey::Desktop => FocalPoint::normalized(76.0, 10.0),
            ViewportKey::Default => FocalPoint::center(),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function overlayVariablesMobile(): array
    {
        return [
            'svc-program-mask-fade-start' => '78%',
            'svc-program-mask-fade-mid' => '90%',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function overlayVariablesDesktop(): array
    {
        return [
            'svc-program-mask-fade-start' => '80%',
            'svc-program-mask-fade-mid' => '91%',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function articleOverlayCssVariables(): array
    {
        $out = [];
        foreach (self::overlayVariablesMobile() as $base => $value) {
            $out[$base.'-mobile'] = $value;
        }
        foreach (self::overlayVariablesDesktop() as $base => $value) {
            $out[$base.'-desktop'] = $value;
        }

        return $out;
    }

    /**
     * @return list<array{key: string, label: string, width: int, height: int, maxCssPx: int}>
     */
    public static function previewFrames(): array
    {
        return [
            [
                'key' => 'mobile',
                'label' => 'Mobile',
                'width' => 390,
                'height' => (int) round(390 * 1.25),
                'maxCssPx' => 1023,
            ],
            [
                'key' => 'tablet',
                'label' => 'Tablet',
                'width' => 768,
                'height' => (int) round(768 * 0.55),
                'maxCssPx' => 1023,
            ],
            [
                'key' => 'desktop',
                'label' => 'Desktop',
                'width' => 1200,
                'height' => (int) round(1200 * 0.43),
                'maxCssPx' => 9999,
            ],
        ];
    }

    public static function viewportKeyForWidth(int $widthPx): ViewportKey
    {
        if ($widthPx <= self::PICTURE_MOBILE_MAX_PX) {
            return ViewportKey::Mobile;
        }

        return ViewportKey::Desktop;
    }

    /**
     * @return array{bottomPercent: float, label: string}
     */
    public static function safeAreaBottomBand(): array
    {
        return ['bottomPercent' => 38.0, 'label' => 'Текст / CTA'];
    }
}
