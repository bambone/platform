<?php

namespace App\MediaPresentation\Profiles;

use App\MediaPresentation\FocalPoint;
use App\MediaPresentation\ViewportFraming;
use App\MediaPresentation\ViewportKey;

/**
 * Slot: service_program_card — ratios/breakpoints are defined here (PHP source of truth).
 * CSS in theme must mirror these numbers for layout; preview uses the same constants.
 *
 * **Framing scale** (user zoom on top of object-fit: cover): single source for PHP, Filament, JS clamp.
 * Stored per viewport in legacy JSON key {@code viewport_focal_map} as part of {@see ViewportFraming}.
 *
 * **expert_auto + advocate_editorial:** оба подключают {@code tenant-expert-auto.css} при {@code body.expert-auto-theme}; карточки программ и framing-CSS должны оставаться идентичными на сайте.
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

    /** User zoom multiplier on top of cover-fit; must be ≥ 1. */
    public const FRAMING_SCALE_MIN = 1.0;

    public const FRAMING_SCALE_MAX = 1.5;

    public const FRAMING_SCALE_STEP = 0.05;

    public const FRAMING_SCALE_DEFAULT = 1.0;

    /**
     * Media box height for program cards: &gt;1.0 = taller area, &lt;1.0 = shorter, 1.0 = theme baseline.
     * Resolver outputs precomputed {@code w}/{@code h} for {@code aspect-ratio} (see {@see mediaAspectHeightsForServiceCard}).
     */
    public const HEIGHT_FACTOR_MIN = 0.5;

    public const HEIGHT_FACTOR_MAX = 2.0;

    public const HEIGHT_FACTOR_STEP = 0.05;

    public const HEIGHT_FACTOR_DEFAULT = 1.0;

    /**
     * Baseline (height_factor = 1) aspect for CSS — must stay in sync with {@code tenant-expert-auto} media rules.
     */
    public const MEDIA_BASE_W_NARROW = 3.0;

    public const MEDIA_BASE_H_NARROW = 2.2;

    public const MEDIA_BASE_W_SM = 7.0;

    public const MEDIA_BASE_H_SM = 4.4;

    public const MEDIA_BASE_W_DESKTOP = 2.1;

    public const MEDIA_BASE_H_DESKTOP = 1.1;

    public const MEDIA_BASE_W_FEATURED = 2.25;

    /**
     * @return array{
     *     w_mobile: float,
     *     h_mobile: float,
     *     w_desktop: float,
     *     h_desktop: float
     * }
     */
    public static function mediaAspectDimensionsForServiceCard(float $heightFactorMobile, float $heightFactorDesktop): array
    {
        $fM = ViewportFraming::clampHeightFactor($heightFactorMobile);
        $fD = ViewportFraming::clampHeightFactor($heightFactorDesktop);

        return [
            'w_mobile' => self::MEDIA_BASE_W_NARROW,
            'h_mobile' => self::MEDIA_BASE_H_NARROW * $fM,
            'w_desktop' => self::MEDIA_BASE_W_DESKTOP,
            'h_desktop' => self::MEDIA_BASE_H_DESKTOP * $fD,
        ];
    }

    /**
     * Preview frame pixel size: same w/h as public CSS (narrow, sm, or desktop) — one formula per key.
     *
     * @return array{width: int, height: int}
     */
    public static function serviceCardPreviewFrameSizeForKey(string $key, int $refWidth, float $heightFactorMobile, float $heightFactorDesktop): array
    {
        $fM = ViewportFraming::clampHeightFactor($heightFactorMobile);
        $fD = ViewportFraming::clampHeightFactor($heightFactorDesktop);
        $w = (float) $refWidth;
        if ($key === 'desktop') {
            $hNum = self::MEDIA_BASE_H_DESKTOP * $fD;
            $wNum = self::MEDIA_BASE_W_DESKTOP;
        } elseif ($key === 'tablet') {
            $hNum = self::MEDIA_BASE_H_SM * $fM;
            $wNum = self::MEDIA_BASE_W_SM;
        } else {
            $hNum = self::MEDIA_BASE_H_NARROW * $fM;
            $wNum = self::MEDIA_BASE_W_NARROW;
        }
        $height = (int) round($w * $hNum / $wNum);

        return ['width' => (int) $w, 'height' => $height];
    }

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
