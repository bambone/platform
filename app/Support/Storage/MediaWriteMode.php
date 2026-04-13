<?php

namespace App\Support\Storage;

enum MediaWriteMode: string
{
    case LocalOnly = 'local_only';
    case R2Only = 'r2_only';
    case Dual = 'dual';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
