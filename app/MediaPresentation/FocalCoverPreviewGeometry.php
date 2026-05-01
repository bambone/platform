<?php

namespace App\MediaPresentation;

/**
 * Cover / object-position geometry for Filament drag preview (same formulas as {@see resources/js/service-program-cover-focal-editor.js}).
 */
final class FocalCoverPreviewGeometry
{
    private const EPS = 1e-6;

    /**
     * @return array{scale: float, dispW: float, dispH: float}
     */
    public static function coverDisplaySize(float $iw, float $ih, float $frameW, float $frameH): array
    {
        if ($iw <= 0 || $ih <= 0 || $frameW <= 0 || $frameH <= 0) {
            return ['scale' => 1.0, 'dispW' => $frameW, 'dispH' => $frameH];
        }
        $scale = max($frameW / $iw, $frameH / $ih);

        return [
            'scale' => $scale,
            'dispW' => $iw * $scale,
            'dispH' => $ih * $scale,
        ];
    }

    /**
     * Базовый масштаб по высоте кадра (как object-fit:contain по высоте); ширина может быть меньше кадра.
     *
     * @return array{scale: float, dispW: float, dispH: float}
     */
    public static function heightFitDisplaySize(float $iw, float $ih, float $frameW, float $frameH): array
    {
        if ($iw <= 0 || $ih <= 0 || $frameW <= 0 || $frameH <= 0) {
            return ['scale' => 1.0, 'dispW' => $frameW, 'dispH' => $frameH];
        }
        $scale = $frameH / $ih;

        return [
            'scale' => $scale,
            'dispW' => $iw * $scale,
            'dispH' => $frameH,
        ];
    }

    /**
     * @param  'cover'|'height_fit'  $geometryMode
     *
     * @return array{scale: float, dispW: float, dispH: float}
     */
    public static function previewDisplaySize(float $iw, float $ih, float $frameW, float $frameH, string $geometryMode = 'cover'): array
    {
        if ($geometryMode === 'height_fit') {
            return self::heightFitDisplaySize($iw, $ih, $frameW, $frameH);
        }

        return self::coverDisplaySize($iw, $ih, $frameW, $frameH);
    }

    /**
     * Translate from "center" position (px=50, py=50) in CSS pixels.
     * User scale above 1 enlarges the displayed image vs base cover-fit; hero allows below 1 to fit height.
     *
     * @param  float  $userScaleMin  Same lower bound as Filament / {@see ViewportFraming::clampScale} (cards: 1, hero: 0.5).
     * @param  'cover'|'height_fit'  $geometryMode  Desktop hero preview / public: height_fit; иначе cover.
     *
     * @return array{tx: float, ty: float}
     */
    public static function translateFromFocal(float $px, float $py, float $frameW, float $frameH, float $iw, float $ih, float $userScale = 1.0, float $userScaleMin = 1.0, string $geometryMode = 'cover'): array
    {
        $d = self::previewDisplaySize($iw, $ih, $frameW, $frameH, $geometryMode);
        $us = max($userScaleMin, $userScale);
        $dispW = $d['dispW'] * $us;
        $dispH = $d['dispH'] * $us;
        $tx = (abs($frameW - $dispW) < self::EPS) ? 0.0 : (($px / 100.0) - 0.5) * ($frameW - $dispW);
        $ty = (abs($frameH - $dispH) < self::EPS) ? 0.0 : (($py / 100.0) - 0.5) * ($frameH - $dispH);

        return ['tx' => $tx, 'ty' => $ty];
    }

    /**
     * @param  'cover'|'height_fit'  $geometryMode
     *
     * @return array{x: float, y: float}
     */
    public static function focalFromTranslate(float $tx, float $ty, float $frameW, float $frameH, float $iw, float $ih, float $userScale = 1.0, float $userScaleMin = 1.0, string $geometryMode = 'cover'): array
    {
        $d = self::previewDisplaySize($iw, $ih, $frameW, $frameH, $geometryMode);
        $us = max($userScaleMin, $userScale);
        $dispW = $d['dispW'] * $us;
        $dispH = $d['dispH'] * $us;
        $px = abs($frameW - $dispW) < self::EPS ? 50.0 : 50.0 + ($tx / ($frameW - $dispW)) * 100.0;
        $py = abs($frameH - $dispH) < self::EPS ? 50.0 : 50.0 + ($ty / ($frameH - $dispH)) * 100.0;

        return [
            'x' => max(0.0, min(100.0, $px)),
            'y' => max(0.0, min(100.0, $py)),
        ];
    }

    /**
     * Clamp translate so focal stays in [0,100] (cover constraint).
     *
     * @param  'cover'|'height_fit'  $geometryMode
     *
     * @return array{tx: float, ty: float}
     */
    public static function clampTranslate(float $tx, float $ty, float $frameW, float $frameH, float $iw, float $ih, float $userScale = 1.0, float $userScaleMin = 1.0, string $geometryMode = 'cover'): array
    {
        $f = self::focalFromTranslate($tx, $ty, $frameW, $frameH, $iw, $ih, $userScale, $userScaleMin, $geometryMode);
        $clamped = FocalPoint::normalized($f['x'], $f['y']);

        return self::translateFromFocal($clamped->x, $clamped->y, $frameW, $frameH, $iw, $ih, $userScale, $userScaleMin, $geometryMode);
    }

    /**
     * Round focal for storage (one decimal).
     *
     * @return array{x: float, y: float}
     */
    public static function focalForCommit(float $x, float $y): array
    {
        $p = FocalPoint::normalized($x, $y);

        return ['x' => $p->x, 'y' => $p->y];
    }
}
