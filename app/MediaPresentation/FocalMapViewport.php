<?php

namespace App\MediaPresentation;

/**
 * Pick focal / framing from legacy key {@code viewport_focal_map} using the same key order as {@see ServiceProgramCardPresentationResolver}.
 */
final class FocalMapViewport
{
    /**
     * @param  array<string, array{x: float, y: float, scale?: float}>  $map
     */
    public static function pickFocalFromMap(array $map, ViewportKey $viewport): ?FocalPoint
    {
        $vf = self::pickFramingFromMap($map, $viewport);

        return $vf?->toFocalPoint();
    }

    /**
     * @param  array<string, array<string, mixed>>  $map
     * @param  ?float  $framingScaleMin  null → program-card bounds
     */
    public static function pickFramingFromMap(
        array $map,
        ViewportKey $viewport,
        ?float $framingScaleMin = null,
        ?float $framingScaleMax = null,
        ?float $framingScaleStep = null,
    ): ?ViewportFraming {
        $order = match ($viewport) {
            ViewportKey::Tablet => ['tablet', 'mobile', 'default'],
            ViewportKey::Mobile => ['mobile', 'default'],
            ViewportKey::Desktop => ['desktop', 'default'],
            ViewportKey::Default => ['default'],
        };
        foreach ($order as $k) {
            if (! isset($map[$k])) {
                continue;
            }
            $vf = ViewportFraming::fromArray($map[$k], $framingScaleMin, $framingScaleMax, $framingScaleStep);
            if ($vf !== null) {
                return $vf;
            }
        }

        return null;
    }
}
