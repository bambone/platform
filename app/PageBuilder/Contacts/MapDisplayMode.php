<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

enum MapDisplayMode: string
{
    case ButtonOnly = 'button_only';
    case EmbedOnly = 'embed_only';
    case EmbedAndButton = 'embed_and_button';

    public static function tryFromMixed(mixed $v): ?self
    {
        if (! is_string($v) || $v === '') {
            return null;
        }

        return self::tryFrom($v);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromDataJson(array $data): self
    {
        $direct = self::tryFromMixed($data['map_display_mode'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        $legacy = is_string($data['map_embed_mode'] ?? null) ? trim((string) $data['map_embed_mode']) : '';

        return match ($legacy) {
            'button_only' => self::ButtonOnly,
            'embed' => self::EmbedOnly,
            'auto', '' => self::EmbedAndButton,
            default => self::EmbedAndButton,
        };
    }
}
