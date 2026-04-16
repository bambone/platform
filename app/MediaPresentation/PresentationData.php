<?php

namespace App\MediaPresentation;

use Illuminate\Support\Facades\Log;

/**
 * Stored presentation JSON for a slot on an entity (versioned).
 *
 * @phpstan-type FocalMap array<string, array{x: float, y: float}>
 */
final class PresentationData implements \JsonSerializable
{
    public const CURRENT_VERSION = 1;

    /**
     * @param  FocalMap  $viewportFocalMap  keys: default|mobile|tablet|desktop
     */
    public function __construct(
        public int $version,
        public array $viewportFocalMap,
    ) {}

    /**
     * @param  array<string, mixed>|null  $row
     */
    public static function fromArray(?array $row): self
    {
        if ($row === null || $row === []) {
            return self::empty();
        }
        $version = (int) ($row['version'] ?? self::CURRENT_VERSION);
        $map = self::normalizeFocalMap(is_array($row['viewport_focal_map'] ?? null) ? $row['viewport_focal_map'] : []);

        return new self($version !== 0 ? $version : self::CURRENT_VERSION, $map);
    }

    public static function empty(): self
    {
        return new self(self::CURRENT_VERSION, []);
    }

    /**
     * @param  array<string, mixed>  $map
     * @return FocalMap
     */
    private static function normalizeFocalMap(array $map): array
    {
        $out = [];
        foreach ($map as $k => $v) {
            if (! is_string($k) || ! is_array($v)) {
                continue;
            }
            if (ViewportKey::tryFrom($k) === null) {
                Log::debug('media_presentation.viewport_focal_map.unknown_key', ['key' => $k]);

                continue;
            }
            $fp = FocalPoint::tryFromArray($v);
            if ($fp !== null) {
                $out[$k] = $fp->toArray();
            }
        }

        return $out;
    }

    /**
     * @return array{version: int, viewport_focal_map: FocalMap}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'viewport_focal_map' => $this->viewportFocalMap,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
