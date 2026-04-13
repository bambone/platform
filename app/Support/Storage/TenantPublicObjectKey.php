<?php

namespace App\Support\Storage;

use InvalidArgumentException;

/**
 * Normalizes and validates bucket/mirror object keys before mapping to filesystem paths or URLs.
 */
final class TenantPublicObjectKey
{
    /**
     * @return non-empty-string
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $key): string
    {
        $key = str_replace('\\', '/', $key);
        $key = trim($key);
        if ($key === '') {
            throw new InvalidArgumentException('Object key is empty.');
        }
        if (str_starts_with($key, '/')) {
            throw new InvalidArgumentException('Object key must not start with "/".');
        }
        if (str_contains($key, "\0")) {
            throw new InvalidArgumentException('Object key must not contain null bytes.');
        }
        if (str_contains($key, '..')) {
            throw new InvalidArgumentException('Object key must not contain "..".');
        }
        $segments = explode('/', $key);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw new InvalidArgumentException('Object key segment ".." is not allowed.');
            }
        }

        return $key;
    }

    /**
     * Web-facing URLs under /media/ are only allowed for tenant public site assets.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException
     */
    public static function assertWebExposedTenantPublicKey(string $key, int $tenantId): string
    {
        $key = self::normalize($key);
        $expectedPrefix = 'tenants/'.$tenantId.'/public/';
        if (! str_starts_with($key, $expectedPrefix)) {
            throw new InvalidArgumentException('Key is not in web-exposed tenant public namespace.');
        }
        $rest = substr($key, strlen($expectedPrefix));
        if ($rest === '') {
            throw new InvalidArgumentException('Key must include a path under tenants/{id}/public/.');
        }

        return $key;
    }

    /**
     * Regex-friendly check without throwing (for resolver).
     */
    public static function isWebExposedTenantPublicKey(string $key, int $tenantId): bool
    {
        try {
            self::assertWebExposedTenantPublicKey($key, $tenantId);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
