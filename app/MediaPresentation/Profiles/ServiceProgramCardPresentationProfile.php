<?php

namespace App\MediaPresentation\Profiles;

use App\MediaPresentation\FocalPoint;
use App\MediaPresentation\ViewportKey;

/**
 * Slot: service_program_card — ratios/breakpoints are defined here (PHP source of truth).
 * CSS in theme must mirror these numbers for layout; preview uses the same constants.
 *
 * Tablet vs runtime: the public site maps layout width to only {@link ViewportKey::Mobile} or {@link ViewportKey::Desktop}
 * ({@see viewportKeyForWidth}). {@link ViewportKey::Tablet} is not a third runtime bucket — it exists for
 * {@code viewport_focal_map} fallback ({@code tablet → mobile → default}) and for the admin tablet row in {@see previewFrames()}.
 */
final class ServiceProgramCardPresentationProfile
{
    public const SLOT_ID = 'service_program_card';

    /** Matches {@code <picture>} switch and focal columns (mobile vs desktop). */
    public const PICTURE_MOBILE_MAX_PX = 1023;

    public const DESKTOP_MIN_PX = 1024;

    /**
     * Default focal when no data (neutral; avoids legacy "top-heavy" crop).
     */
    public static function defaultFocalForViewport(ViewportKey $key): FocalPoint
    {
        return match ($key) {
            ViewportKey::Mobile => FocalPoint::normalized(50.0, 52.0),
            ViewportKey::Tablet => FocalPoint::normalized(50.0, 50.0),
            ViewportKey::Desktop => FocalPoint::normalized(50.0, 48.0),
            ViewportKey::Default => FocalPoint::center(),
        };
    }

    /**
     * Overlay parameters for CSS variables (profile-owned, not editor-owned on MVP).
     *
     * @return array<string, string>
     */
    public static function overlayVariablesMobile(): array
    {
        return [
            'svc-program-mask-fade-start' => '52%',
            'svc-program-mask-fade-mid' => '70%',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function overlayVariablesDesktop(): array
    {
        return [
            'svc-program-mask-fade-start' => '55%',
            'svc-program-mask-fade-mid' => '72%',
        ];
    }

    /**
     * Overlay CSS vars for public {@code article} inline style (mobile + desktop suffixes; derived from overlayVariablesMobile / overlayVariablesDesktop).
     *
     * @return array<string, string> names without leading {@code --}
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
     * Preview frames for admin (same aspect ratios as theme CSS for this slot).
     * Keys include {@code tablet} for Filament; site runtime still uses only {@see viewportKeyForWidth} (mobile vs desktop).
     *
     * @return list<array{key: string, label: string, width: int, height: int, maxCssPx: int}>
     */
    public static function previewFrames(): array
    {
        return [
            [
                'key' => 'mobile',
                'label' => 'Mobile',
                'width' => 360,
                'height' => (int) round(360 * 2.2 / 3),
                'maxCssPx' => 639,
            ],
            [
                'key' => 'tablet',
                'label' => 'Tablet',
                'width' => 600,
                'height' => (int) round(600 * 4.4 / 7),
                'maxCssPx' => 1023,
            ],
            [
                'key' => 'desktop',
                'label' => 'Desktop',
                'width' => 900,
                'height' => (int) round(900 * 1.1 / 2.1),
                'maxCssPx' => 9999,
            ],
        ];
    }

    /**
     * Map layout width to viewport for public-site focal resolution (MVP: two buckets).
     * {@link ViewportKey::Tablet} is intentionally not returned here — tablet widths use {@link ViewportKey::Mobile} behaviour.
     */
    public static function viewportKeyForWidth(int $widthPx): ViewportKey
    {
        if ($widthPx <= self::PICTURE_MOBILE_MAX_PX) {
            return ViewportKey::Mobile;
        }

        return ViewportKey::Desktop;
    }

    /**
     * Safe-area overlay hints for preview (approximate text/CTA band from bottom).
     *
     * @return array{bottomPercent: float, label: string}
     */
    public static function safeAreaBottomBand(): array
    {
        return ['bottomPercent' => 38.0, 'label' => 'Текст / CTA'];
    }
}
