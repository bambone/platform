<?php

namespace App\Filament\Tenant\PageBuilder;

/**
 * Normalizes strings for section admin cards (no HTML in list UI).
 */
final class SectionAdminPlainText
{
    public static function line(string $value): string
    {
        $v = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');

        return $v;
    }

    /**
     * @return list<string>
     */
    public static function splitPreviewToLines(string $preview, int $maxLines = 4, int $maxLineLen = 140): array
    {
        $preview = self::line($preview);
        if ($preview === '') {
            return [];
        }
        $parts = preg_split('/\s*[·|]\s*/u', $preview) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = self::line($part);
            if ($part === '') {
                continue;
            }
            if (strlen($part) > $maxLineLen) {
                $part = substr($part, 0, $maxLineLen).'…';
            }
            $out[] = $part;
            if (count($out) >= $maxLines) {
                break;
            }
        }

        return $out !== [] ? $out : [strlen($preview) > $maxLineLen ? substr($preview, 0, $maxLineLen).'…' : $preview];
    }
}
