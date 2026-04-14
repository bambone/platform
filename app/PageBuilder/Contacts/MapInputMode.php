<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

/**
 * How the editor wants the combined map paste interpreted (default: auto).
 */
enum MapInputMode: string
{
    case Auto = 'auto';
    case Url = 'url';
    case Iframe = 'iframe';

    public static function fromDataJson(array $data): self
    {
        $v = $data['map_input_mode'] ?? self::Auto->value;
        if (is_string($v) && self::tryFrom($v) !== null) {
            return self::from($v);
        }

        return self::Auto;
    }
}
