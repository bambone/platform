<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

enum MapProvider: string
{
    case None = 'none';
    case Yandex = 'yandex';
    case Google = 'google';
    case TwoGis = '2gis';

    public function labelRu(): string
    {
        return match ($this) {
            self::None => '',
            self::Yandex => 'Яндекс Карты',
            self::Google => 'Google Maps',
            self::TwoGis => '2ГИС',
        };
    }

    public function actionLabelRu(): string
    {
        return match ($this) {
            self::None => 'Открыть карту',
            self::Yandex => 'Открыть в Яндекс Картах',
            self::Google => 'Открыть в Google Maps',
            self::TwoGis => 'Открыть в 2ГИС',
        };
    }

    public static function tryFromMixed(mixed $v): ?self
    {
        if (! is_string($v) || $v === '') {
            return null;
        }

        return self::tryFrom($v);
    }
}
