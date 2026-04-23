<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

/**
 * Локальные копии для карточек/галерей: при наличии GD — даунскейл по ширине, иначе сырой файл.
 */
final class BlackDuckProofImageNormalizer
{
    public const DEFAULT_MAX_WIDTH = 1920;

    /**
     * @return array{0: string, 1: string}|null [bytes, contentType] или null при ошибке
     */
    public static function normalizeFile(string $absolutePath, int $maxWidth = self::DEFAULT_MAX_WIDTH): ?array
    {
        if (! is_readable($absolutePath)) {
            return null;
        }
        $ext = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) {
            return null;
        }
        $raw = @file_get_contents($absolutePath);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        if (! extension_loaded('gd') || $maxWidth < 1) {
            return [
                $raw,
                match ($ext) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    'avif' => 'image/avif',
                    default => 'image/jpeg',
                },
            ];
        }
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            return [
                $raw,
                match ($ext) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    'avif' => 'image/avif',
                    default => 'image/jpeg',
                },
            ];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 1 || $h < 1) {
            imagedestroy($img);

            return null;
        }
        if ($w <= $maxWidth) {
            imagedestroy($img);

            return [
                $raw,
                match ($ext) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    'avif' => 'image/avif',
                    default => 'image/jpeg',
                },
            ];
        }
        $nw = $maxWidth;
        $nh = (int) round($h * ($nw / $w));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($img);

            return null;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        ob_start();
        imagejpeg($dst, null, 85);
        imagedestroy($dst);
        $jpeg = (string) ob_get_clean();

        return $jpeg !== '' ? [$jpeg, 'image/jpeg'] : null;
    }

    public static function outputExtensionForContentType(string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
