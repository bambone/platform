<?php

namespace App\MediaPresentation;

/**
 * Focal point in percentage space (0–100). Coordinates normalized to one decimal for storage/compare.
 */
final readonly class FocalPoint implements \JsonSerializable
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    public static function normalized(float $x, float $y): self
    {
        return new self(
            round(max(0.0, min(100.0, $x)), 1),
            round(max(0.0, min(100.0, $y)), 1),
        );
    }

    /**
     * @param  array{x?: mixed, y?: mixed}|null  $data
     */
    public static function tryFromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }
        if (! isset($data['x'], $data['y'])) {
            return null;
        }
        if (! is_numeric($data['x']) || ! is_numeric($data['y'])) {
            return null;
        }

        return self::normalized((float) $data['x'], (float) $data['y']);
    }

    public static function center(): self
    {
        return new self(50.0, 50.0);
    }

    public function toCssObjectPosition(): string
    {
        return $this->x.'% '.$this->y.'%';
    }

    /**
     * @return array{x: float, y: float}
     */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
