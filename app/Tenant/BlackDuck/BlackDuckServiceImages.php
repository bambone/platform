<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Support\Storage\TenantStorage;

/**
 * Картинки услуг (экспорт с ПК → {@see BlackDuckDuckMediaImporter::importServiceImagesFromDirectory}).
 * Логические ключи в хранилище: {@code site/brand/services/{key}.jpg}.
 *
 * Полный Q1-набор из папки «Услуги»; соответствует публичной сетке на главной и /uslugi.
 */
final class BlackDuckServiceImages
{
    public const PUBLIC_PREFIX = 'site/brand/services';

    /**
     * Имя файла в исходной папке «Услуги» (как на диске у оператора).
     * Один source может повторяться (один jpg в две услуги — разные ключи в storage).
     *
     * @return array<string, string> slug матрицы → basename
     */
    public static function sourceBasenameByMatrixSlug(): array
    {
        return [
            'detejling-mojka' => '-xiU8EqWw_4.jpg',
            'setki-radiatora' => 'nUEShyuf1es.jpg',
            'antidozhd' => 'krkfay15MUU.jpg',
            'remont-skolov' => 'Z1OAEGVFWGE.jpg',
            'shumka' => 'c2OkULFLdN0.jpg',
            'kozha-keramika' => 'lQd_vTq43sw.jpg',
            'tonirovka' => 'k3k6hwq4xhY.jpg',
            'keramika' => 'lQd_vTq43sw.jpg',
            'restavratsiya-kozhi' => 'Qio4Hjl_ThY.jpg',
            'himchistka-diskov' => 'lXeqc_uyGaI.jpg',
            'bronirovanie-salona' => 'mnmhkxRve0M.jpg',
            'himchistka-kuzova' => 'hi9-o3KQgsU.jpg',
            'ppf' => 'pFKFEbBxW28.jpg',
            'podkapotnaya-himchistka' => 'lsoa9VLqFQ0.jpg',
            'polirovka-kuzova' => 'ji8Scdr6n-w.jpg',
            'himchistka-salona' => 'XAANboIdKHQ.jpg',
            'pdr' => 'BbROXFIoOUY.jpg',
            // Дубликат визуала: та же витринная плашка, что и керамика (как в исходном сете из 18 картинок)
            'predprodazhnaya' => '7OwXQxEWlBY.jpg',
            '#expert-inquiry' => 'RnGd2iFIFWg.jpg',
        ];
    }

    /**
     * Доп. имена файла, если YouTube-id переименован вручную на диске.
     *
     * @return list<string>
     */
    public static function alternateSourceBasenamesForMatrixSlug(string $matrixSlug): array
    {
        return match ($matrixSlug) {
            'kozha-keramika' => ['IQd_vTqI3sw.jpg', 'lqd_vtq43sw.jpg'],
            'keramika' => ['7OwXQxEWlBY.jpg', '7owxqxewlby.jpg', 'IQd_vTqI3sw.jpg'],
            'restavratsiya-kozhi' => ['Qio4HjLThY.jpg', 'qio4hjl_thy.jpg'],
            'himchistka-diskov' => ['IXeqc_uyGaI.jpg', 'lxeqc_uygai.jpg'],
            'podkapotnaya-himchistka' => [
                'lsoa9VLqFQ20.jpg',
                'lsoa9VLqFQ2O.jpg',
                'lsoa9VLqFQ2o.jpg',
                'lsoa9VLqFQ20.JPG',
            ],
            'polirovka-kuzova' => [
                'ji8Sclr6n-w.jpg',
                'ji8Sclr6n_w.jpg',
                'ji8Sclr6nw.jpg',
                'J18Sclr6n-w.jpg',
            ],
            default => [],
        };
    }

    /**
     * @return list<string> список basenames: основной, затем запасные
     */
    public static function sourceBasenameCandidatesForMatrixSlug(string $matrixSlug): array
    {
        $m = self::sourceBasenameByMatrixSlug();
        if (! isset($m[$matrixSlug])) {
            return [];
        }
        $c = array_merge([$m[$matrixSlug]], self::alternateSourceBasenamesForMatrixSlug($matrixSlug));

        return array_values(array_unique($c));
    }

    public static function storageKeyForMatrixSlug(string $matrixSlug): string
    {
        if ($matrixSlug === '#expert-inquiry') {
            return 'vinil';
        }

        return ltrim($matrixSlug, '#/');
    }

    /**
     * Один URL для фона hero посадочных: WebP 1600 / бандл, иначе легаси service-landing-hero.
     */
    public static function firstServiceLandingShadePath(int $tenantId): ?string
    {
        $ts = TenantStorage::forTrusted($tenantId);
        foreach (['site/brand/hero-1600.webp', 'site/brand/hero-1916.jpg', 'site/brand/hero-1916.webp'] as $p) {
            if ($ts->existsPublic($p)) {
                return $p;
            }
        }

        return self::firstExistingServiceLandingHeaderPath($tenantId);
    }

    /**
     * SEO / превью: JPEG или WebP для одиночного {@code img}, если нет бандла в {@see BlackDuckHomeHeroBundle::heroSectionFragmentForTenant}.
     */
    public static function firstHomeExpertHeroLogicalPath(int $tenantId): ?string
    {
        $ts = TenantStorage::forTrusted($tenantId);
        foreach (['site/brand/hero-1916.jpg', 'site/brand/hero-1916.webp', 'site/brand/hero-1600.webp'] as $p) {
            if ($ts->existsPublic($p)) {
                return $p;
            }
        }
        $legacy = self::firstExistingServiceLandingHeaderPath($tenantId);
        if ($legacy !== null) {
            return $legacy;
        }
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $p = 'site/brand/hero.'.$ext;
            if ($ts->existsPublic($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Легаси: один файл {@code service-landing-hero.*} (до бандла WebP).
     */
    public static function firstExistingServiceLandingHeaderPath(int $tenantId): ?string
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $stem = BlackDuckContentConstants::SERVICE_LANDING_HEADER_STEM;
        foreach (['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'] as $ext) {
            $p = $stem.'.'.$ext;
            if ($ts->existsPublic($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Первый существующий публичный путь к картинке услуги или null.
     */
    public static function firstExistingPublicPath(int $tenantId, string $matrixSlug): ?string
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $key = self::storageKeyForMatrixSlug($matrixSlug);
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $p = self::PUBLIC_PREFIX.'/'.$key.'.'.$ext;
            if ($ts->existsPublic($p)) {
                return $p;
            }
        }

        return null;
    }
}
