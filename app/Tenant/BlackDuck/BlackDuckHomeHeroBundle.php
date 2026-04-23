<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\Tenant;
use App\Support\Storage\TenantPublicMediaWriter;
use App\Support\Storage\TenantStorage;

/**
 * Responsive hero: WebP + JPEG в {@code site/brand/}; исходники в папке «Услуги» с теми же именами файлов.
 *
 * Положите рядом канонич.: hero-1916.webp, … — или алиасы {@see SOURCE_BASENAME_ALIASES} (как в папке «Услуги»).
 */
final class BlackDuckHomeHeroBundle
{
    /**
     * Логические ключи на public-диске тенанта (после {@code site/brand/}).
     *
     * @var array<string, string> role => path
     */
    public const STORAGE_LOGICAL = [
        'w1916' => 'site/brand/hero-1916.webp',
        'w1600' => 'site/brand/hero-1600.webp',
        'w900' => 'site/brand/hero-900.webp',
        'w900_1200' => 'site/brand/hero-900-1200.webp',
        'jpg' => 'site/brand/hero-1916.jpg',
    ];

    /**
     * Допустимые имена файлов в исходнике; первое совпадение с каноном в {@see STORAGE_LOGICAL}.
     *
     * @var array<string, list<string>> role => [basename, …]
     */
    private const SOURCE_BASENAME_ALIASES = [
        'w1916' => ['blackduck-hero-desktop-1916.webp'],
        'w1600' => ['blackduck-hero-desktop-1600.webp'],
        'w900' => ['blackduck-hero-mobile-900.webp'],
        'w900_1200' => ['blackduck-hero-mobile-portrait-900x1200.webp'],
        'jpg' => ['blackduck-hero-desktop-1916.jpg'],
    ];

    /**
     * @return list<string>
     */
    public static function sourceBasenameCandidatesForRole(string $role): array
    {
        if (! isset(self::STORAGE_LOGICAL[$role])) {
            return [];
        }
        $primary = basename(self::STORAGE_LOGICAL[$role]);
        $aliases = self::SOURCE_BASENAME_ALIASES[$role] ?? [];

        return array_values(array_unique(array_merge([$primary], $aliases)));
    }

    /**
     * Имена файлов, которые ищет импорт (канон + алиасы).
     *
     * @return list<string>
     */
    public static function expectedSourceBasenames(): array
    {
        $out = [];
        foreach (array_keys(self::STORAGE_LOGICAL) as $role) {
            $out = array_merge($out, self::sourceBasenameCandidatesForRole((string) $role));
        }

        return array_values(array_unique($out));
    }

    /**
     * Фрагмент {@code data_json} для expert_hero: {@code hero_responsive} + {@code hero_image_url} (JPEG для SEO/превью).
     *
     * @return array{hero_responsive: array<string, string>, hero_image_url: string}|null
     */
    public static function heroSectionFragmentForTenant(int $tenantId): ?array
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $resolved = [];
        foreach (self::STORAGE_LOGICAL as $role => $logical) {
            if ($ts->existsPublic($logical)) {
                $resolved[$role] = $logical;
            }
        }
        if ($resolved === []) {
            return null;
        }
        $preview = $resolved['jpg'] ?? $resolved['w1916'] ?? $resolved['w1600'] ?? $resolved['w900'] ?? $resolved['w900_1200'] ?? null;
        if ($preview === null) {
            return null;
        }

        return [
            'hero_responsive' => $resolved,
            'hero_image_url' => $resolved['jpg'] ?? $resolved['w1916'] ?? $resolved['w1600'] ?? $resolved['w900'] ?? $preview,
        ];
    }

    /**
     * Удаляет тяжёлый PNG/старый одиночный hero и прежний service-landing-hero до импорта бандла.
     */
    public static function deleteLegacyHeroAssets(Tenant $tenant): void
    {
        $ts = TenantStorage::forTrusted($tenant);
        $writer = app(TenantPublicMediaWriter::class);
        $tid = (int) $tenant->id;
        $legacySingle = ['site/brand/hero.png', 'site/brand/hero.jpg', 'site/brand/hero.jpeg', 'site/brand/hero.webp', 'site/brand/hero.gif'];
        foreach ($legacySingle as $logical) {
            if ($ts->existsPublic($logical)) {
                $writer->deletePublicObjectKey($tid, $ts->publicPath($logical));
            }
        }
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif'] as $ext) {
            $logical = BlackDuckContentConstants::SERVICE_LANDING_HEADER_STEM.'.'.$ext;
            if ($ts->existsPublic($logical)) {
                $writer->deletePublicObjectKey($tid, $ts->publicPath($logical));
            }
        }
    }

    public static function findSourceFileForRole(string $absoluteDir, string $role): ?string
    {
        $dir = rtrim($absoluteDir, DIRECTORY_SEPARATOR);
        if (! is_dir($dir)) {
            return null;
        }
        foreach (self::sourceBasenameCandidatesForRole($role) as $basename) {
            $abs = $dir.DIRECTORY_SEPARATOR.$basename;
            if (is_file($abs) && is_readable($abs)) {
                return $abs;
            }
            $lower = strtolower($basename);
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                if (strtolower((string) $f) === $lower) {
                    $cand = $dir.DIRECTORY_SEPARATOR.$f;
                    if (is_file($cand) && is_readable($cand)) {
                        return $cand;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string> role => logical path записанных файлов
     */
    public static function importFromDirectory(Tenant $tenant, string $absoluteDir, bool $dryRun): array
    {
        $dir = rtrim($absoluteDir, DIRECTORY_SEPARATOR);
        if (! is_dir($dir)) {
            return [];
        }
        if ($dryRun) {
            $out = [];
            foreach (self::STORAGE_LOGICAL as $role => $logical) {
                if (self::findSourceFileForRole($dir, (string) $role) !== null) {
                    $out[$role] = $logical;
                }
            }

            return $out;
        }

        $ts = TenantStorage::forTrusted($tenant);
        $toImport = [];
        foreach (self::STORAGE_LOGICAL as $role => $logical) {
            $abs = self::findSourceFileForRole($dir, (string) $role);
            if ($abs !== null) {
                $toImport[$role] = ['logical' => $logical, 'abs' => $abs];
            }
        }
        if ($toImport === []) {
            return [];
        }

        self::deleteLegacyHeroAssets($tenant);

        $out = [];
        foreach ($toImport as $role => $pair) {
            $logical = $pair['logical'];
            $abs = $pair['abs'];
            $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) {
                continue;
            }
            $bytes = @file_get_contents($abs);
            if (! is_string($bytes) || $bytes === '') {
                continue;
            }
            $contentType = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'avif' => 'image/avif',
                default => 'image/jpeg',
            };
            if (! $ts->putPublic($logical, $bytes, [
                'ContentType' => $contentType,
                'visibility' => 'public',
            ])) {
                continue;
            }
            $out[$role] = $logical;
        }

        return $out;
    }
}
