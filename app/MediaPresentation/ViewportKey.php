<?php

namespace App\MediaPresentation;

/**
 * Viewport keys for focal maps (domain vocabulary — not narrow/wide).
 */
enum ViewportKey: string
{
    case Default = 'default';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Desktop = 'desktop';

    /**
     * @return list<string>
     */
    public static function storageKeys(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
