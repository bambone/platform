<?php

namespace App\MediaPresentation;

use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;

/**
 * Per-viewport framing for a presentation slot: pan (x/y %) + user zoom (scale ≥ 1) + optional program-card media height.
 *
 * Not to be confused with {@see FocalPoint} (percent point only).
 * Persisted JSON key for height: {@code height_factor}; camelCase may appear during local dev — {@see fromArray} accepts both.
 */
final readonly class ViewportFraming implements \JsonSerializable
{
    public function __construct(
        public float $x,
        public float $y,
        public float $scale,
        public float $heightFactor = 1.0,
    ) {}

    /**
     * @param  array<string, mixed>|null  $row  v1: {x,y} only; v2: {x,y,scale}; v3: + height_factor
     */
    public static function fromArray(?array $row): ?self
    {
        if ($row === null) {
            return null;
        }
        $fp = FocalPoint::tryFromArray($row);
        if ($fp === null) {
            return null;
        }
        $scale = isset($row['scale']) && is_numeric($row['scale'])
            ? (float) $row['scale']
            : ServiceProgramCardPresentationProfile::FRAMING_SCALE_DEFAULT;
        $hf = self::heightFactorFromRow($row);

        return self::normalized($fp->x, $fp->y, $scale, $hf);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function heightFactorFromRow(array $row): float
    {
        if (isset($row['height_factor']) && is_numeric($row['height_factor'])) {
            return self::clampHeightFactor((float) $row['height_factor']);
        }
        if (isset($row['heightFactor']) && is_numeric($row['heightFactor'])) {
            return self::clampHeightFactor((float) $row['heightFactor']);
        }

        return 1.0;
    }

    public static function normalized(float $x, float $y, float $scale, ?float $heightFactor = null): self
    {
        $p = FocalPoint::normalized($x, $y);
        $s = self::clampScale($scale);
        $h = $heightFactor === null ? 1.0 : self::clampHeightFactor($heightFactor);

        return new self($p->x, $p->y, $s, $h);
    }

    public static function clampScale(float $scale): float
    {
        $min = ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN;
        $max = ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX;
        $s = max($min, min($max, $scale));
        $step = ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP;

        return round($s / $step) * $step;
    }

    /**
     * Round scale for persisted storage (aligned with step).
     */
    public static function scaleForCommit(float $scale): float
    {
        return self::clampScale($scale);
    }

    public static function clampHeightFactor(float $h): float
    {
        $min = ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MIN;
        $max = ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MAX;
        $s = max($min, min($max, $h));
        $step = ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_STEP;

        return round($s / $step) * $step;
    }

    public static function heightFactorForCommit(float $h): float
    {
        return self::clampHeightFactor($h);
    }

    public function toFocalPoint(): FocalPoint
    {
        return FocalPoint::normalized($this->x, $this->y);
    }

    /**
     * @return array{x: float, y: float, scale: float, height_factor: float}
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'scale' => $this->scale,
            'height_factor' => $this->heightFactor,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
