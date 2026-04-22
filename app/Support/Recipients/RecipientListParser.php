<?php

namespace App\Support\Recipients;

use Illuminate\Support\Str;

/**
 * Parses comma-separated or JSON-array lists of string tokens (emails, Telegram chat ids, etc.).
 *
 * - trim each entry, drop empties, preserve string type (negative group chat ids stay strings)
 * - unique while keeping first occurrence order
 */
final class RecipientListParser
{
    /**
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if (Str::startsWith($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = array_values(array_filter(array_map('trim', array_map('strval', $decoded)), static fn (string $s): bool => $s !== ''));

                return self::uniqueOrdered($items);
            }
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $s): bool => $s !== ''));

        return self::uniqueOrdered($items);
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private static function uniqueOrdered(array $items): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            if (isset($seen[$item])) {
                continue;
            }
            $seen[$item] = true;
            $out[] = $item;
        }

        return $out;
    }
}
