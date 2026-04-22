<?php

namespace App\MediaPresentation;

use Illuminate\Support\Facades\Log;

/**
 * Stored presentation JSON for a slot on an entity (versioned).
 *
 * **Legacy JSON key** {@code viewport_focal_map}: historically "focal map"; values are now
 * {@see ViewportFraming} objects ({@code x}, {@code y}, {@code scale}). The key name is kept for DB compatibility.
 *
 * @phpstan-type ViewportFramingMap array<string, array{x: float, y: float, scale: float, height_factor?: float}>
 */
final class PresentationData implements \JsonSerializable
{
    public const CURRENT_VERSION = 2;

    /**
     * @param  ViewportFramingMap  $viewportFocalMap  keys: default|mobile|tablet|desktop
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
        // Mistaken JSON: [{ "version": … }] instead of { "version": … }
        if (array_is_list($row) && count($row) === 1 && is_array($row[0]) && isset($row[0]['version'])) {
            $row = $row[0];
        }
        $version = (int) ($row['version'] ?? self::CURRENT_VERSION);
        $map = self::normalizeViewportFramingMap(is_array($row['viewport_focal_map'] ?? null) ? $row['viewport_focal_map'] : []);

        return new self($version !== 0 ? $version : self::CURRENT_VERSION, $map);
    }

    public static function empty(): self
    {
        return new self(self::CURRENT_VERSION, []);
    }

    /**
     * @param  array<string, mixed>  $map
     * @return ViewportFramingMap
     */
    private static function normalizeViewportFramingMap(array $map): array
    {
        if (isset($map['moblie']) && is_array($map['moblie']) && ! isset($map['mobile'])) {
            $map['mobile'] = $map['moblie'];
        }
        unset($map['moblie']);

        $out = [];
        foreach ($map as $k => $v) {
            if (! is_string($k) || ! is_array($v)) {
                continue;
            }
            if (ViewportKey::tryFrom($k) === null) {
                Log::debug('media_presentation.viewport_focal_map.unknown_key', ['key' => $k]);

                continue;
            }
            $vf = ViewportFraming::fromArray($v);
            if ($vf !== null) {
                $out[$k] = $vf->toArray();
            }
        }

        return $out;
    }

    /**
     * @return array{version: int, viewport_focal_map: ViewportFramingMap}
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
