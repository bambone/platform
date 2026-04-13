<?php

namespace App\Support\Storage;

enum MediaDeliveryMode: string
{
    case Local = 'local';
    case R2 = 'r2';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
