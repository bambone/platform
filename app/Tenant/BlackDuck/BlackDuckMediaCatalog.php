<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Support\Storage\TenantStorage;

/**
 * JSON {@code site/brand/media-catalog.json}: единый curated-манифест. Внешние URL недопустимы в logical_path для proof.
 *
 * @phpstan-type DerivativeRow array{w: int, logical_path: string}
 * @phpstan-type AssetRow array{
 *   role: string,
 *   service_slug?: string|null,
 *   page_slug?: string|null,
 *   sort_order?: int,
 *   is_featured?: bool,
 *   caption?: string,
 *   alt?: string,
 *   before_after_group?: string|null,
 *   logical_path?: string,
 *   poster_logical_path?: string|null,
 *   source_ref?: string|null,
 *   kind?: string,
 *   title?: string,
 *   summary?: string,
 *   service_label?: string,
 *   tags?: list<string>,
 *   aspect_hint?: string|null,
 *   display_variant?: string,
 *   badge?: string,
 *   cta_label?: string,
 *   show_on_home?: bool|null,
 *   show_on_works?: bool|null,
 *   show_on_service?: bool|null,
 *   works_group?: string|null,
 *   derivatives?: list<DerivativeRow>
 * }
 */
final class BlackDuckMediaCatalog
{
    /**
     * v3: display metadata + visibility flags; {@see normalizeAssetRow()} дополняет v2 без миграции файла.
     */
    public const SCHEMA_VERSION = 3;

    public const CATALOG_LOGICAL = 'site/brand/media-catalog.json';

    /**
     * @var list<string>
     */
    public const SERVICE_PROOF_LANDING_SLUGS = [
        'ppf',
        'keramika',
        'polirovka-kuzova',
        'himchistka-salona',
        'predprodazhnaya',
        'pdr',
    ];

    /**
     * @return array{version: int, assets: list<AssetRow>}
     */
    public static function loadOrEmpty(int $tenantId): array
    {
        $ts = TenantStorage::forTrusted($tenantId);
        if (! $ts->existsPublic(self::CATALOG_LOGICAL)) {
            return ['version' => self::SCHEMA_VERSION, 'assets' => []];
        }
        $raw = $ts->getPublic(self::CATALOG_LOGICAL);
        if (! is_string($raw) || $raw === '') {
            return ['version' => self::SCHEMA_VERSION, 'assets' => []];
        }
        $d = json_decode($raw, true);
        if (! is_array($d)) {
            return ['version' => self::SCHEMA_VERSION, 'assets' => []];
        }
        $assets = $d['assets'] ?? [];
        if (! is_array($assets)) {
            $assets = [];
        }
        $normalized = [];
        foreach ($assets as $a) {
            if (! is_array($a)) {
                continue;
            }
            $row = self::normalizeAssetRow($a, $tenantId);
            if ($row !== null) {
                $normalized[] = $row;
            }
        }

        return [
            'version' => max(1, (int) ($d['version'] ?? 1)),
            'assets' => $normalized,
        ];
    }

    /**
     * Есть ли валидные curated-записи с локальными путями (generic importer не должен затирать proof).
     */
    public static function hasCuratedManifest(int $tenantId): bool
    {
        foreach (self::loadOrEmpty($tenantId)['assets'] as $a) {
            $role = (string) ($a['role'] ?? '');
            if ($role === '') {
                continue;
            }
            if (self::logicalPathIsUsable($tenantId, (string) ($a['logical_path'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    public static function logicalPathIsUsable(int $tenantId, string $logicalPath): bool
    {
        $logicalPath = trim($logicalPath);
        if ($logicalPath === '' || self::looksLikeRemoteUrl($logicalPath)) {
            return false;
        }
        $ts = TenantStorage::forTrusted($tenantId);
        $path = self::normalizeLogicalKey($logicalPath);

        return $ts->existsPublic($path);
    }

    public static function looksLikeRemoteUrl(string $v): bool
    {
        return preg_match('#^https?://#i', trim($v)) === 1;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return ?AssetRow
     */
    public static function normalizeAssetRow(array $raw, ?int $tenantId = null): ?array
    {
        $role = self::normalizeLegacyRole((string) ($raw['role'] ?? ''));
        if ($role === null || BlackDuckMediaRole::tryFrom($role) === null) {
            return null;
        }
        $logical = trim((string) ($raw['logical_path'] ?? ''));
        if ($logical === '' || self::looksLikeRemoteUrl($logical)) {
            return null;
        }
        $poster = isset($raw['poster_logical_path']) ? trim((string) $raw['poster_logical_path']) : '';
        if ($poster !== '' && self::looksLikeRemoteUrl($poster)) {
            $poster = '';
        }
        if ($poster === '') {
            $poster = trim((string) ($raw['poster_path'] ?? ''));
            if (self::looksLikeRemoteUrl($poster)) {
                $poster = '';
            }
        }
        $serviceSlug = isset($raw['service_slug']) ? trim((string) $raw['service_slug']) : '';
        if ($serviceSlug === '') {
            $serviceSlug = null;
        }
        $pageSlug = isset($raw['page_slug']) ? trim((string) $raw['page_slug']) : '';
        if ($pageSlug === '') {
            $page = trim((string) ($raw['page'] ?? $raw['context'] ?? ''));
            if ($page !== '') {
                $pageSlug = $page;
            }
        }
        if ($pageSlug === '') {
            $pageSlug = null;
        }
        $group = isset($raw['before_after_group']) ? trim((string) $raw['before_after_group']) : '';
        if ($group === '') {
            $bg = trim((string) ($raw['before_group'] ?? ''));
            $ag = trim((string) ($raw['after_group'] ?? ''));
            $group = $bg !== '' ? $bg : ($ag !== '' ? $ag : '');
        }
        $group = $group !== '' ? $group : null;
        $caption = trim((string) ($raw['caption'] ?? ''));
        $alt = trim((string) ($raw['alt'] ?? ''));
        $sourceRef = isset($raw['source_ref']) ? trim((string) $raw['source_ref']) : null;
        if ($sourceRef === '') {
            $sourceRef = null;
        }
        $title = trim((string) ($raw['title'] ?? ''));
        $summary = trim((string) ($raw['summary'] ?? ''));
        $serviceLabel = trim((string) ($raw['service_label'] ?? ''));
        $tagsRaw = $raw['tags'] ?? [];
        $tags = [];
        if (is_array($tagsRaw)) {
            foreach ($tagsRaw as $t) {
                $ts = trim((string) $t);
                if ($ts !== '') {
                    $tags[] = $ts;
                }
            }
        }
        $aspectHint = isset($raw['aspect_hint']) ? trim((string) $raw['aspect_hint']) : '';
        $aspectHint = $aspectHint !== '' ? $aspectHint : null;
        $displayVariant = trim((string) ($raw['display_variant'] ?? ''));
        $badge = trim((string) ($raw['badge'] ?? ''));
        $ctaLabel = trim((string) ($raw['cta_label'] ?? ''));
        $worksGroup = isset($raw['works_group']) ? trim((string) $raw['works_group']) : '';
        $worksGroup = $worksGroup !== '' ? $worksGroup : null;
        $showOnHome = array_key_exists('show_on_home', $raw) ? (bool) $raw['show_on_home'] : null;
        $showOnWorks = array_key_exists('show_on_works', $raw) ? (bool) $raw['show_on_works'] : null;
        $showOnService = array_key_exists('show_on_service', $raw) ? (bool) $raw['show_on_service'] : null;
        $derivatives = self::normalizeDerivatives(
            (int) ($tenantId ?? 0),
            $raw['derivatives'] ?? null,
            $tenantId !== null && $tenantId > 0,
        );

        return [
            'role' => $role,
            'service_slug' => $serviceSlug,
            'page_slug' => $pageSlug,
            'sort_order' => (int) ($raw['sort_order'] ?? 0),
            'is_featured' => (bool) ($raw['is_featured'] ?? false),
            'caption' => $caption,
            'alt' => $alt,
            'before_after_group' => $group,
            'logical_path' => self::normalizeLogicalKey($logical),
            'poster_logical_path' => $poster !== '' ? self::normalizeLogicalKey($poster) : null,
            'source_ref' => $sourceRef,
            'kind' => trim((string) ($raw['kind'] ?? '')),
            'title' => $title,
            'summary' => $summary,
            'service_label' => $serviceLabel,
            'tags' => $tags,
            'aspect_hint' => $aspectHint,
            'display_variant' => $displayVariant,
            'badge' => $badge,
            'cta_label' => $ctaLabel,
            'show_on_home' => $showOnHome,
            'show_on_works' => $showOnWorks,
            'show_on_service' => $showOnService,
            'works_group' => $worksGroup,
            'derivatives' => $derivatives,
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<DerivativeRow>
     */
    public static function normalizeDerivatives(int $tenantId, mixed $raw, bool $validateFiles): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $ts = $validateFiles && $tenantId > 0 ? TenantStorage::forTrusted($tenantId) : null;
        $out = [];
        foreach ($raw as $d) {
            if (! is_array($d)) {
                continue;
            }
            $w = (int) ($d['w'] ?? 0);
            $p = trim((string) ($d['logical_path'] ?? ''));
            if ($w < 1 || $p === '' || self::looksLikeRemoteUrl($p)) {
                continue;
            }
            $p = self::normalizeLogicalKey($p);
            if ($ts !== null && ! $ts->existsPublic($p)) {
                continue;
            }
            $out[] = ['w' => $w, 'logical_path' => $p];
        }
        usort($out, static fn (array $x, array $y): int => $x['w'] <=> $y['w']);

        return $out;
    }

    private static function normalizeLegacyRole(string $role): ?string
    {
        $role = trim($role);
        if ($role === '') {
            return null;
        }
        if (BlackDuckMediaRole::tryFrom($role) !== null) {
            return $role;
        }
        $map = [
            'featured_video' => BlackDuckMediaRole::WorksFeaturedVideo->value,
            'video_poster' => BlackDuckMediaRole::WorksFeaturedPoster->value,
            'works_featured' => BlackDuckMediaRole::WorksFeaturedVideo->value,
            'before_after_before' => BlackDuckMediaRole::WorksBeforeAfterBefore->value,
            'before_after_after' => BlackDuckMediaRole::WorksBeforeAfterAfter->value,
            'home_proof_feature' => BlackDuckMediaRole::HomeProofBefore->value,
        ];

        return $map[$role] ?? null;
    }

    public static function normalizeLogicalKey(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        return $path;
    }

    /**
     * Обложка карточки услуги на главной: role home_service_card, иначе featured service_gallery.
     */
    public static function homeServiceHubImage(int $tenantId, string $serviceSlug): ?string
    {
        $cat = self::loadOrEmpty($tenantId);
        $candidates = [];
        foreach ($cat['assets'] as $a) {
            if (($a['role'] ?? '') !== BlackDuckMediaRole::HomeServiceCard->value) {
                continue;
            }
            if (($a['service_slug'] ?? null) !== $serviceSlug) {
                continue;
            }
            $p = (string) ($a['logical_path'] ?? '');
            if (self::logicalPathIsUsable($tenantId, $p)) {
                $candidates[] = ['sort' => (int) ($a['sort_order'] ?? 0), 'path' => $p];
            }
        }
        usort($candidates, static fn (array $x, array $y): int => $x['sort'] <=> $y['sort']);
        if ($candidates !== []) {
            return $candidates[0]['path'];
        }

        foreach ($cat['assets'] as $a) {
            if (($a['role'] ?? '') !== BlackDuckMediaRole::ServiceGallery->value) {
                continue;
            }
            if (($a['service_slug'] ?? null) !== $serviceSlug) {
                continue;
            }
            if (! ($a['is_featured'] ?? false)) {
                continue;
            }
            $p = (string) ($a['logical_path'] ?? '');
            if (self::logicalPathIsUsable($tenantId, $p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Обложка карточки на /uslugi — та же логика, что {@see homeServiceHubImage()} (каталог + legacy).
     */
    public static function serviceHubCatalogCover(int $tenantId, string $serviceSlug): ?string
    {
        return self::homeServiceHubImage($tenantId, $serviceSlug);
    }

    /**
     * Одна полная пара до/после для главной (home_proof_* , одна группа).
     *
     * @return list<array{before_url: string, after_url: string, caption: string}>
     */
    public static function homeBeforeAfterPairs(int $tenantId): array
    {
        $cat = self::loadOrEmpty($tenantId);
        $beforeByGroup = [];
        $afterByGroup = [];
        foreach ($cat['assets'] as $a) {
            $g = trim((string) ($a['before_after_group'] ?? ''));
            if ($g === '') {
                continue;
            }
            $path = (string) ($a['logical_path'] ?? '');
            if (! self::logicalPathIsUsable($tenantId, $path)) {
                continue;
            }
            if (($a['role'] ?? '') === BlackDuckMediaRole::HomeProofBefore->value) {
                $beforeByGroup[$g] = ['path' => $path, 'sort' => (int) ($a['sort_order'] ?? 0), 'caption' => (string) ($a['caption'] ?? '')];
            }
            if (($a['role'] ?? '') === BlackDuckMediaRole::HomeProofAfter->value) {
                $afterByGroup[$g] = ['path' => $path, 'sort' => (int) ($a['sort_order'] ?? 0), 'caption' => (string) ($a['caption'] ?? '')];
            }
        }
        $pairs = [];
        foreach ($beforeByGroup as $g => $b) {
            if (! isset($afterByGroup[$g])) {
                continue;
            }
            $a = $afterByGroup[$g];
            $cap = $b['caption'] !== '' ? $b['caption'] : $a['caption'];
            $pairs[] = [
                'group' => $g,
                'before_url' => $b['path'],
                'after_url' => $a['path'],
                'caption' => $cap,
                'sort' => $b['sort'] + $a['sort'],
            ];
        }
        usort($pairs, static fn (array $x, array $y): int => $x['sort'] <=> $y['sort']);
        if ($pairs === []) {
            return [];
        }
        $one = $pairs[0];

        return [[
            'before_url' => $one['before_url'],
            'after_url' => $one['after_url'],
            'caption' => $one['caption'],
        ]];
    }

    /**
     * Кейсы для главной: works_case_card / works_gallery, page_slug home или общий (null).
     *
     * @return list<array{vehicle: string, task: string, result: string, duration: string, image_url: string}>
     */
    public static function homeCaseCardItems(int $tenantId, int $limit = 3): array
    {
        return self::collectVisualCaseItems(
            $tenantId,
            $limit,
            static function (array $a): bool {
                $ps = $a['page_slug'] ?? null;
                if ($ps === 'raboty') {
                    return false;
                }

                return true;
            },
            'home',
        );
    }

    /**
     * Карточки «истории» на /raboty (есть title/summary или display_variant card|row).
     *
     * @return list<array<string, mixed>>
     */
    public static function worksStoryCardItems(int $tenantId, int $limit = 8): array
    {
        $all = self::collectVisualCaseItems(
            $tenantId,
            200,
            static function (array $a): bool {
                return ($a['page_slug'] ?? null) !== 'home';
            },
            'works',
        );
        $out = [];
        foreach ($all as $it) {
            $title = trim((string) ($it['title'] ?? ''));
            $summary = trim((string) ($it['summary'] ?? ''));
            $dv = (string) ($it['display_variant'] ?? '');
            if ($title === '' && $summary === '' && ! in_array($dv, ['card', 'row'], true)) {
                continue;
            }
            $out[] = $it;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function worksCaseListItems(int $tenantId, int $limit = 12): array
    {
        return self::worksStoryCardItems($tenantId, min($limit, 12));
    }

    /**
     * Сетка галереи /raboty + ключи для фильтров.
     *
     * @return list<array<string, mixed>>
     */
    public static function worksPortfolioGridItems(int $tenantId, int $max = 48): array
    {
        $items = self::collectVisualCaseItems(
            $tenantId,
            $max,
            static function (array $a): bool {
                return ($a['page_slug'] ?? null) !== 'home';
            },
            'works',
        );
        foreach ($items as &$it) {
            $keys = ['all'];
            $slug = trim((string) ($it['service_slug'] ?? ''));
            if ($slug !== '' && ! str_starts_with($slug, '#')) {
                $keys[] = 'service:'.$slug;
            }
            foreach ($it['tags'] ?? [] as $tg) {
                $ts = trim((string) $tg);
                if ($ts !== '') {
                    $keys[] = 'tag:'.$ts;
                }
            }
            $it['filter_keys'] = array_values(array_unique($keys));
        }
        unset($it);

        return $items;
    }

    /**
     * Чипы фильтрации: из manifest (service_slug + tags), без жёстких списков в Blade.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function worksPortfolioFilterChips(int $tenantId): array
    {
        $cat = self::loadOrEmpty($tenantId);
        $slugSet = [];
        $tagSet = [];
        foreach ($cat['assets'] as $a) {
            $role = (string) ($a['role'] ?? '');
            if (! in_array($role, [BlackDuckMediaRole::WorksCaseCard->value, BlackDuckMediaRole::WorksGallery->value], true)) {
                continue;
            }
            if (($a['show_on_works'] ?? null) === false) {
                continue;
            }
            if (($a['page_slug'] ?? null) === 'home') {
                continue;
            }
            $p = (string) ($a['logical_path'] ?? '');
            if (! self::logicalPathIsUsable($tenantId, $p)) {
                continue;
            }
            $slug = trim((string) ($a['service_slug'] ?? ''));
            if ($slug !== '' && ! str_starts_with($slug, '#')) {
                $slugSet[$slug] = true;
            }
            foreach ($a['tags'] ?? [] as $t) {
                $ts = trim((string) $t);
                if ($ts !== '') {
                    $tagSet[$ts] = true;
                }
            }
        }
        $chips = [['value' => 'all', 'label' => 'Все']];
        $slugOrdered = [];
        foreach (BlackDuckContentConstants::serviceMatrixQ1() as $row) {
            $s = trim((string) ($row['slug'] ?? ''));
            if ($s !== '' && ! str_starts_with($s, '#') && isset($slugSet[$s])) {
                $slugOrdered[] = $s;
            }
        }
        $slugExtras = array_values(array_diff(array_keys($slugSet), $slugOrdered));
        sort($slugExtras, SORT_STRING);
        foreach (array_merge($slugOrdered, $slugExtras) as $slug) {
            $chips[] = [
                'value' => 'service:'.$slug,
                'label' => BlackDuckContentConstants::serviceTitleForSlug($slug),
            ];
        }
        $tagLabels = array_keys($tagSet);
        sort($tagLabels, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($tagLabels as $tag) {
            $chips[] = ['value' => 'tag:'.$tag, 'label' => $tag];
        }

        return $chips;
    }

    /**
     * @param  callable(AssetRow): bool  $include
     * @return list<array<string, mixed>>
     */
    private static function collectVisualCaseItems(
        int $tenantId,
        int $limit,
        callable $include,
        string $visibilityContext = 'works',
    ): array {
        $cat = self::loadOrEmpty($tenantId);
        $rows = [];
        foreach ($cat['assets'] as $a) {
            $role = (string) ($a['role'] ?? '');
            if (! in_array($role, [BlackDuckMediaRole::WorksCaseCard->value, BlackDuckMediaRole::WorksGallery->value], true)) {
                continue;
            }
            if ($visibilityContext === 'home' && (($a['show_on_home'] ?? null) === false)) {
                continue;
            }
            if ($visibilityContext === 'works' && (($a['show_on_works'] ?? null) === false)) {
                continue;
            }
            if (! $include($a)) {
                continue;
            }
            $p = (string) ($a['logical_path'] ?? '');
            if (! self::logicalPathIsUsable($tenantId, $p)) {
                continue;
            }
            $title = trim((string) ($a['title'] ?? ''));
            $caption = trim((string) ($a['caption'] ?? ''));
            $summary = trim((string) ($a['summary'] ?? ''));
            $task = $title !== '' ? $title : $caption;
            $tags = [];
            if (is_array($a['tags'] ?? null)) {
                foreach ($a['tags'] as $t) {
                    $ts = trim((string) $t);
                    if ($ts !== '') {
                        $tags[] = $ts;
                    }
                }
            }
            $deriv = is_array($a['derivatives'] ?? null) ? $a['derivatives'] : [];
            $srcset = BlackDuckProofDisplay::srcsetFromDerivatives($tenantId, $deriv);
            $slug = $a['service_slug'] ?? null;
            $rows[] = [
                'sort' => ((int) ($a['sort_order'] ?? 0)) * 10 + (($a['is_featured'] ?? false) ? -100000 : 0),
                'vehicle' => trim((string) ($a['works_group'] ?? '')),
                'task' => $task,
                'title' => $title,
                'summary' => $summary,
                'result' => '',
                'duration' => '',
                'image_url' => $p,
                'service_slug' => $slug,
                'service_label' => trim((string) ($a['service_label'] ?? '')),
                'tags' => $tags,
                'badge' => trim((string) ($a['badge'] ?? '')),
                'srcset' => $srcset,
                'sizes' => BlackDuckProofDisplay::defaultGallerySizes(),
                'aspect_ratio' => BlackDuckProofDisplay::aspectRatioCss(isset($a['aspect_hint']) ? (string) $a['aspect_hint'] : null),
                'alt' => trim((string) ($a['alt'] ?? '')),
                'cta_label' => trim((string) ($a['cta_label'] ?? '')),
                'display_variant' => trim((string) ($a['display_variant'] ?? '')),
            ];
        }
        usort($rows, static fn (array $x, array $y): int => $x['sort'] <=> $y['sort']);
        $out = [];
        foreach ($rows as $r) {
            unset($r['sort']);
            $out[] = $r;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Пары до/после для /raboty.
     *
     * @return list<array{before_url: string, after_url: string, caption: string}>
     */
    public static function worksBeforeAfterPairs(int $tenantId): array
    {
        $cat = self::loadOrEmpty($tenantId);
        $beforeByGroup = [];
        $afterByGroup = [];
        foreach ($cat['assets'] as $a) {
            $g = trim((string) ($a['before_after_group'] ?? ''));
            if ($g === '') {
                continue;
            }
            $path = (string) ($a['logical_path'] ?? '');
            if (! self::logicalPathIsUsable($tenantId, $path)) {
                continue;
            }
            if (($a['role'] ?? '') === BlackDuckMediaRole::WorksBeforeAfterBefore->value) {
                $beforeByGroup[$g] = ['path' => $path, 'sort' => (int) ($a['sort_order'] ?? 0), 'caption' => (string) ($a['caption'] ?? '')];
            }
            if (($a['role'] ?? '') === BlackDuckMediaRole::WorksBeforeAfterAfter->value) {
                $afterByGroup[$g] = ['path' => $path, 'sort' => (int) ($a['sort_order'] ?? 0), 'caption' => (string) ($a['caption'] ?? '')];
            }
        }
        $out = [];
        foreach ($beforeByGroup as $g => $b) {
            if (! isset($afterByGroup[$g])) {
                continue;
            }
            $a = $afterByGroup[$g];
            $cap = $b['caption'] !== '' ? $b['caption'] : $a['caption'];
            $out[] = [
                'before_url' => $b['path'],
                'after_url' => $a['path'],
                'caption' => $cap,
                '_sort' => $b['sort'] + $a['sort'],
            ];
        }
        usort($out, static fn (array $x, array $y): int => ($x['_sort'] ?? 0) <=> ($y['_sort'] ?? 0));

        return array_map(static function (array $p): array {
            unset($p['_sort']);

            return $p;
        }, $out);
    }

    /**
     * Видео+постер для блока works_hero на /raboty.
     *
     * @return array{video?: string, poster?: string}
     */
    public static function worksFeaturedHeroMedia(int $tenantId): array
    {
        $cat = self::loadOrEmpty($tenantId);
        $video = '';
        $poster = '';
        foreach ($cat['assets'] as $a) {
            if (($a['role'] ?? '') === BlackDuckMediaRole::WorksFeaturedVideo->value) {
                $v = (string) ($a['logical_path'] ?? '');
                $low = strtolower($v);
                if (self::logicalPathIsUsable($tenantId, $v) && (str_ends_with($low, '.mp4') || str_ends_with($low, '.webm'))) {
                    $video = $v;
                    $pl = (string) ($a['poster_logical_path'] ?? '');
                    if ($pl !== '' && self::logicalPathIsUsable($tenantId, $pl)) {
                        $poster = $pl;
                    }
                }
            }
        }
        if ($poster === '') {
            foreach ($cat['assets'] as $a) {
                if (($a['role'] ?? '') !== BlackDuckMediaRole::WorksFeaturedPoster->value) {
                    continue;
                }
                $pl = (string) ($a['logical_path'] ?? '');
                if (self::logicalPathIsUsable($tenantId, $pl)) {
                    $poster = $pl;
                    break;
                }
            }
        }
        $out = [];
        if ($video !== '' && $poster !== '') {
            $out['video'] = $video;
            $out['poster'] = $poster;
        }

        return $out;
    }

    /**
     * @deprecated use {@see self::worksFeaturedHeroMedia()}
     *
     * @return array{video?: string, poster?: string}
     */
    public static function featuredVideoForPage(int $tenantId, string $pageContext): array
    {
        if (strtolower($pageContext) !== 'raboty') {
            return [];
        }

        return self::worksFeaturedHeroMedia($tenantId);
    }

    /**
     * @return list<array{ logical_path: string, caption?: string }>
     */
    public static function serviceGalleryImagePaths(int $tenantId, string $serviceSlug): array
    {
        $full = self::serviceGalleryDisplayItems($tenantId, $serviceSlug);

        return array_slice(array_map(static fn (array $r): array => [
            'logical_path' => $r['logical_path'],
            'caption' => $r['caption'],
        ], $full), 0, 5);
    }

    /**
     * Полные поля для service_proof (подписи, srcset, alt).
     *
     * @return list<array<string, mixed>>
     */
    public static function serviceGalleryDisplayItems(int $tenantId, string $serviceSlug): array
    {
        $cat = self::loadOrEmpty($tenantId);
        $out = [];
        foreach ($cat['assets'] as $a) {
            if (($a['role'] ?? '') !== BlackDuckMediaRole::ServiceGallery->value) {
                continue;
            }
            if (($a['service_slug'] ?? null) !== $serviceSlug) {
                continue;
            }
            if (($a['show_on_service'] ?? null) === false) {
                continue;
            }
            $p = trim((string) ($a['logical_path'] ?? ''));
            if (! self::logicalPathIsUsable($tenantId, $p)) {
                continue;
            }
            $deriv = is_array($a['derivatives'] ?? null) ? $a['derivatives'] : [];
            $out[] = [
                'sort' => (int) ($a['sort_order'] ?? 0),
                'logical_path' => $p,
                'caption' => trim((string) ($a['caption'] ?? '')),
                'title' => trim((string) ($a['title'] ?? '')),
                'summary' => trim((string) ($a['summary'] ?? '')),
                'alt' => trim((string) ($a['alt'] ?? '')),
                'srcset' => BlackDuckProofDisplay::srcsetFromDerivatives($tenantId, $deriv),
                'sizes' => BlackDuckProofDisplay::defaultGallerySizes(),
                'aspect_ratio' => BlackDuckProofDisplay::aspectRatioCss(isset($a['aspect_hint']) ? (string) $a['aspect_hint'] : null),
            ];
        }
        usort($out, static fn (array $x, array $y): int => $x['sort'] <=> $y['sort']);

        return array_slice($out, 0, 5);
    }

    /**
     * Опциональное видео на посадочной услуги (оба файла локально).
     *
     * @return array{video?: string, poster?: string}
     */
    public static function serviceFeaturedVideoMedia(int $tenantId, string $serviceSlug): array
    {
        $cat = self::loadOrEmpty($tenantId);
        foreach ($cat['assets'] as $a) {
            if (($a['role'] ?? '') !== BlackDuckMediaRole::ServiceFeaturedVideo->value) {
                continue;
            }
            if (($a['service_slug'] ?? null) !== $serviceSlug) {
                continue;
            }
            $v = (string) ($a['logical_path'] ?? '');
            $poster = (string) ($a['poster_logical_path'] ?? '');
            $low = strtolower($v);
            if (! self::logicalPathIsUsable($tenantId, $v) || ! (str_ends_with($low, '.mp4') || str_ends_with($low, '.webm'))) {
                return [];
            }
            if ($poster === '' || ! self::logicalPathIsUsable($tenantId, $poster)) {
                return [];
            }

            return ['video' => $v, 'poster' => $poster];
        }

        return [];
    }

    /**
     * @param  list<AssetRow>  $assets
     */
    public static function saveCatalog(int $tenantId, int $version, array $assets): bool
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $payload = json_encode([
            'version' => $version,
            'assets' => array_values($assets),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($payload === false) {
            return false;
        }

        return $ts->putPublic(self::CATALOG_LOGICAL, $payload, [
            'ContentType' => 'application/json',
            'visibility' => 'public',
        ]);
    }
}
