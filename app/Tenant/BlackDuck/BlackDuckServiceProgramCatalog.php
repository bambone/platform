<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Единая точка чтения каталога Black Duck. Контракт:
 *
 * - {@see self::databaseHasCatalog} — в БД есть хотя бы одна строка `tenant_service_programs` (каталог заведён).
 *   Пока true, **не** откатываемся к PHP-реестру для публичной логики: пустой список = осознанно пусто.
 * - {@see self::hasVisibleCatalogPrograms} — есть публично видимые услуги (`is_visible`).
 * - CTA на карточке: {@see self::primaryCardCtaUrl} — без посадочной ведёт на inquiry, не на /slug.
 * - Форма «контакты»: {@see self::inquiryFormServiceOptions} — все видимые услуги; отдельно {@see self::inquiryFormLandingServiceOptions} — только с посадочной.
 */
final class BlackDuckServiceProgramCatalog
{
    public static function databaseHasCatalog(int $tenantId): bool
    {
        if (! Schema::hasTable('tenant_service_programs')) {
            return false;
        }

        return TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    public static function hasVisibleCatalogPrograms(int $tenantId): bool
    {
        if (! Schema::hasTable('tenant_service_programs')) {
            return false;
        }

        return TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->exists();
    }

    /**
     * @deprecated use {@see self::hasVisibleCatalogPrograms()} or {@see self::databaseHasCatalog()}
     */
    public static function hasPrograms(int $tenantId): bool
    {
        return self::hasVisibleCatalogPrograms($tenantId);
    }

    /**
     * @return Collection<int, TenantServiceProgram>
     */
    public static function allProgramsOrdered(int $tenantId): Collection
    {
        return TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TenantServiceProgram>
     */
    public static function visibleProgramsOrdered(int $tenantId): Collection
    {
        return TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Посадочные (slug), для которых предусмотрены секция `service_proof` и её синхронизация с медиакаталогом.
     * При {@see databaseHasCatalog}: все **видимые** программы с `has_landing` (кроме псевдо `#…`).
     * Без каталога в БД — legacy-набор {@see BlackDuckMediaCatalog::defaultServiceProofSlugsForLegacy()}.
     *
     * @return list<string>
     */
    public static function serviceProofTargetLandingSlugs(int $tenantId): array
    {
        if (self::databaseHasCatalog($tenantId)) {
            $out = [];
            foreach (self::visibleProgramsOrdered($tenantId) as $p) {
                $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
                if (! (bool) ($meta['has_landing'] ?? true)) {
                    continue;
                }
                $slug = (string) $p->slug;
                if (str_starts_with($slug, '#')) {
                    continue;
                }
                $out[] = $slug;
            }

            return array_values(array_unique($out));
        }

        return BlackDuckMediaCatalog::defaultServiceProofSlugsForLegacy();
    }

    public static function primaryCardCtaUrl(string $slug, bool $hasLanding): string
    {
        if (str_starts_with($slug, '#')) {
            return BlackDuckContentConstants::PRIMARY_LEAD_URL;
        }
        if (! $hasLanding) {
            return BlackDuckContentConstants::contactsInquiryUrlForServiceSlug($slug);
        }

        return '/'.$slug;
    }

    /**
     * @return array{0: bool, 1: bool} [online_booking, needs_confirmation]
     */
    public static function bookingUIFromMode(string $mode): array
    {
        $m = trim($mode);

        return match (strtolower($m)) {
            'instant' => [true, false],
            'quote' => [false, true],
            default => [false, true],
        };
    }

    /**
     * @return list<array{slug: string, title: string, blurb: string, booking_mode: string, has_landing: bool}>
     */
    public static function legacyMatrixQ1ForTenant(int $tenantId): array
    {
        if (! self::databaseHasCatalog($tenantId)) {
            return BlackDuckContentConstants::serviceMatrixQ1();
        }
        $out = [];
        foreach (self::visibleProgramsOrdered($tenantId) as $p) {
            $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
            $out[] = [
                'slug' => (string) $p->slug,
                'title' => (string) $p->title,
                'blurb' => (string) ($p->teaser ?? ''),
                'booking_mode' => (string) ($meta['booking_mode'] ?? ''),
                'has_landing' => (bool) ($meta['has_landing'] ?? true),
            ];
        }

        return $out;
    }

    /**
     * Видимые программы в формате, совместимом с реестром (структурные циклы, FAQ; без тихого fallback, если БД пуста по смыслу).
     *
     * @return list<array{slug: string, title: string, blurb: string, has_landing: bool, booking_mode: string}>
     */
    public static function asRegistryShapedRows(int $tenantId): array
    {
        if (! self::databaseHasCatalog($tenantId)) {
            return BlackDuckServiceRegistry::all();
        }
        $out = [];
        foreach (self::visibleProgramsOrdered($tenantId) as $p) {
            $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
            $out[] = [
                'slug' => (string) $p->slug,
                'title' => (string) $p->title,
                'blurb' => (string) ($p->teaser ?? ''),
                'has_landing' => (bool) ($meta['has_landing'] ?? true),
                'booking_mode' => (string) ($meta['booking_mode'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{slug: string, title: string, blurb: string, booking_mode: string, has_landing: bool}>
     */
    public static function homePreviewRowsExcludingPseudopages(int $tenantId): array
    {
        $out = [];
        foreach (self::legacyMatrixQ1ForTenant($tenantId) as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if (str_starts_with($slug, '#')) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Селектор формы: все **видимые** услуги (в т.ч. без посадочной) — валидные slug для prefill/заявки.
     *
     * @return list<array{slug: string, title: string}>
     */
    public static function inquiryFormServiceOptions(int $tenantId): array
    {
        if (! self::databaseHasCatalog($tenantId)) {
            return self::inquiryFormProgramRowsFromRegistry();
        }
        $rows = [];
        foreach (self::visibleProgramsOrdered($tenantId) as $p) {
            if (str_starts_with((string) $p->slug, '#')) {
                continue;
            }
            $rows[] = [
                'slug' => (string) $p->slug,
                'title' => (string) $p->title,
            ];
        }

        return $rows;
    }

    /**
     * Только услуги с посадочной (узкий набор, например ссылки «ещё на сайте»).
     *
     * @return list<array{slug: string, title: string}>
     */
    public static function inquiryFormLandingServiceOptions(int $tenantId): array
    {
        if (! self::databaseHasCatalog($tenantId)) {
            return BlackDuckServiceRegistry::inquiryFormLandingOptions();
        }
        $rows = [];
        foreach (self::visibleProgramsOrdered($tenantId) as $p) {
            $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
            if (! (bool) ($meta['has_landing'] ?? false)) {
                continue;
            }
            if (str_starts_with((string) $p->slug, '#')) {
                continue;
            }
            $rows[] = [
                'slug' => (string) $p->slug,
                'title' => (string) $p->title,
            ];
        }

        return $rows;
    }

    /**
     * @deprecated use {@see self::inquiryFormLandingServiceOptions()} (узкий) or {@see self::inquiryFormServiceOptions()} (форма)
     */
    public static function inquiryFormLandingOptions(int $tenantId): array
    {
        return self::inquiryFormLandingServiceOptions($tenantId);
    }

    /**
     * @return list<array{slug: string, title: string}>
     */
    private static function inquiryFormProgramRowsFromRegistry(): array
    {
        $out = [];
        foreach (BlackDuckServiceRegistry::all() as $r) {
            if (str_starts_with((string) ($r['slug'] ?? ''), '#')) {
                continue;
            }
            $out[] = [
                'slug' => (string) $r['slug'],
                'title' => (string) $r['title'],
            ];
        }
        usort(
            $out,
            static fn (array $a, array $b): int => $a['title'] <=> $b['title'],
        );

        return $out;
    }

    /**
     * @return ?array{slug: string, title: string, has_landing: bool, blurb: string, booking_mode: string, short_title?: string, included_items?: list<mixed>}&array<string, mixed>
     */
    public static function rowBySlug(int $tenantId, string $slug): ?array
    {
        if (! self::databaseHasCatalog($tenantId)) {
            return BlackDuckServiceRegistry::rowBySlug($slug);
        }
        $p = TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->where('is_visible', true)
            ->first();
        if ($p === null) {
            return null;
        }
        $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];

        return [
            'slug' => (string) $p->slug,
            'title' => (string) $p->title,
            'short_title' => (string) ($meta['short_title'] ?? $p->title),
            'blurb' => (string) ($p->teaser ?? ''),
            'body_intro' => (string) ($p->description ?? ''),
            'booking_mode' => (string) ($meta['booking_mode'] ?? ''),
            'has_landing' => (bool) ($meta['has_landing'] ?? true),
            'included_items' => is_array($meta['included_items'] ?? null) ? $meta['included_items'] : [],
        ];
    }

    public static function serviceTitleForSlug(int $tenantId, string $slug): string
    {
        if (self::databaseHasCatalog($tenantId)) {
            $title = TenantServiceProgram::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->value('title');
            if (is_string($title) && $title !== '') {
                return $title;
            }
        }

        return BlackDuckServiceRegistry::serviceTitleForSlug($slug);
    }

    /**
     * Публичная строка «цена от…» на карточке: 1) {@code public_price_anchor} в meta;
     * 2) иначе форматированный {@see TenantServiceProgram::formattedPriceLabel} с {@code price_prefix};
     * 3) иначе legacy-якорь из реестра.
     */
    public static function publicPriceAnchorForSlug(int $tenantId, string $slug): ?string
    {
        $p = self::databaseHasCatalog($tenantId)
            ? TenantServiceProgram::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->where('is_visible', true)
                ->first()
            : null;
        if ($p !== null) {
            $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
            $v = trim((string) ($meta['public_price_anchor'] ?? ''));
            if ($v !== '') {
                return $v;
            }
            if ($p->price_amount !== null) {
                $tenant = Tenant::query()->find($tenantId);
                $formatted = $p->formattedPriceLabel($tenant);
                if ($formatted !== null && $formatted !== '') {
                    $prefix = trim((string) ($p->price_prefix ?? ''));

                    return $prefix !== '' ? $prefix.' '.$formatted : $formatted;
                }
            }
        }

        return BlackDuckServiceRegistry::publicPriceAnchorForSlug($slug);
    }

    public static function homeCardSubtitle(
        int $tenantId,
        string $slug,
        string $fallbackBlurb,
    ): string {
        if (! self::databaseHasCatalog($tenantId)) {
            $preview = BlackDuckContentConstants::homeServiceCardPreviewSubtitlesBySlug();

            return (string) ($preview[$slug] ?? $fallbackBlurb);
        }
        $p = TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->where('is_visible', true)
            ->first();
        if ($p === null) {
            $preview = BlackDuckContentConstants::homeServiceCardPreviewSubtitlesBySlug();

            return (string) ($preview[$slug] ?? $fallbackBlurb);
        }
        $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
        $sub = trim((string) ($meta['home_card_subtitle'] ?? ''));
        if ($sub !== '') {
            return $sub;
        }

        return $fallbackBlurb;
    }

    /**
     * Обложка карточки хаба (главная, /uslugi): {@see TenantServiceProgram::cover_image_ref}, если файл доступен, иначе легаси-цепочка {@see BlackDuckServiceImages::firstServiceHubCardPublicPath}.
     */
    public static function publicServiceHubCardImageLogicalPath(int $tenantId, string $slug): ?string
    {
        $cover = self::tryUsableProgramCoverLogicalPath($tenantId, $slug);
        if ($cover !== null) {
            return $cover;
        }

        return BlackDuckServiceImages::firstServiceHubCardPublicPath($tenantId, $slug);
    }

    /**
     * Фон hero посадочной услуги: обложка программы, иначе общий shade, иначе картинка матрицы по slug.
     */
    public static function serviceLandingHeroBackgroundLogicalPath(int $tenantId, string $slug): ?string
    {
        $cover = self::tryUsableProgramCoverLogicalPath($tenantId, $slug);
        if ($cover !== null) {
            return $cover;
        }
        $shade = BlackDuckServiceImages::firstServiceLandingShadePath($tenantId);
        if ($shade !== null) {
            return $shade;
        }

        return BlackDuckServiceImages::firstExistingPublicPath($tenantId, $slug);
    }

    private static function tryUsableProgramCoverLogicalPath(int $tenantId, string $slug): ?string
    {
        if (! self::databaseHasCatalog($tenantId) || str_starts_with($slug, '#')) {
            return null;
        }
        $p = TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();
        if ($p === null) {
            return null;
        }
        $ref = trim((string) ($p->cover_image_ref ?? ''));
        if ($ref === '' || ! BlackDuckMediaCatalog::logicalPathIsUsable($tenantId, $ref)) {
            return null;
        }

        return BlackDuckMediaCatalog::normalizeLogicalKey($ref);
    }
}
