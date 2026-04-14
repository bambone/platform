<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

/**
 * Which input won when resolving map_public_url (persisted for status/debug).
 */
enum MapSourceKind: string
{
    case Url = 'url';
    case Iframe = 'iframe';

    public function usedSourceMessageRu(): string
    {
        return match ($this) {
            self::Url => 'Использован источник: публичная ссылка',
            self::Iframe => 'Использован источник: код карты',
        };
    }
}
