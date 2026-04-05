<?php

namespace App\Services\Seo;

/**
 * Relative path for a named route, or null if the route is missing or not a sane tenant path.
 */
final class TenantSeoSafeRoutePath
{
    /**
     * For llms and similar: skip root "/" so it is not confused with a failed route.
     */
    public static function relativeOrNull(string $name): ?string
    {
        $path = self::resolveRelative($name);
        if ($path === null || $path === '/') {
            return null;
        }

        return $path;
    }

    /**
     * For lint: "/" is valid (named home route).
     */
    public static function forLint(string $name): ?string
    {
        return self::resolveRelative($name);
    }

    private static function resolveRelative(string $name): ?string
    {
        try {
            $path = route($name, [], false);
            if (! is_string($path) || $path === '' || ! str_starts_with($path, '/')) {
                return null;
            }

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
