<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

enum MapProviderSupportsEmbedState: string
{
    case Full = 'full';
    case Limited = 'limited';
    case None = 'none';

    public static function forProvider(MapProvider $provider): self
    {
        return match ($provider) {
            MapProvider::Yandex => self::Full,
            MapProvider::Google => self::Limited,
            // Потребительские ссылки 2ГИС — link-first; embed только для отдельных URL (embed.2gis.com) / будущих интеграций.
            MapProvider::TwoGis => self::None,
            MapProvider::None => self::None,
        };
    }
}
