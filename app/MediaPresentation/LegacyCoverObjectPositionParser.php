<?php

namespace App\MediaPresentation;

/**
 * Whitelist-only parser for legacy {@code cover_object_position} strings.
 * Anything else returns null (caller applies safe fallback).
 *
 * Dynamic form {@code center NN%} accepts a decimal fraction (e.g. {@code 18.25%}); leading zeros like {@code 050%} are not matched.
 */
final class LegacyCoverObjectPositionParser
{
    /**
     * Known presets from historical Filament selects (and common synonyms).
     *
     * @var array<string, array{x: float, y: float}>
     */
    private const PRESET_TO_FOCAL = [
        'center top' => ['x' => 50.0, 'y' => 0.0],
        'center 22%' => ['x' => 50.0, 'y' => 22.0],
        'center 30%' => ['x' => 50.0, 'y' => 30.0],
        'center center' => ['x' => 50.0, 'y' => 50.0],
        'center 72%' => ['x' => 50.0, 'y' => 72.0],
        'center bottom' => ['x' => 50.0, 'y' => 100.0],
        'center 18%' => ['x' => 50.0, 'y' => 18.0],
    ];

    public static function parse(?string $raw): ?FocalPoint
    {
        $s = trim((string) $raw);
        if ($s === '' || strcasecmp($s, 'auto') === 0) {
            return null;
        }

        $key = strtolower($s);
        if (isset(self::PRESET_TO_FOCAL[$key])) {
            $p = self::PRESET_TO_FOCAL[$key];

            return FocalPoint::normalized($p['x'], $p['y']);
        }

        // Single-form: "center NN%" (vertical only), horizontal fixed at 50.
        if (preg_match('/^center\s+(\d{1,3}(?:\.\d+)?)\s*%$/i', $s, $m)) {
            return FocalPoint::normalized(50.0, (float) $m[1]);
        }

        return null;
    }
}
