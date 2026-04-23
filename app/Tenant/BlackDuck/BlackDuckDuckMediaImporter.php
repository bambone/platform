<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Http\Controllers\HomeController;
use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Сканирует каталог (например, экспорт с ПК) и копирует jpg/png/webp в {@code site/brand/hub/NN.ext},
 * затем прописывает пути в секциях hub/кейсов/до–после.
 *
 * Предпочтительно для Black Duck: {@see importServiceImagesFromDirectory} (по slug матрицы). Generic-импорт — запасной
 * режим: исключает лого/hero-бандл/именованные сервисы, чтобы не заливать в hub чужие файлы из той же папки.
 */
final class BlackDuckDuckMediaImporter
{
    private const HUB_LOGICAL = 'site/brand/hub';

    /** @var list<string> */
    private const EXCLUDED_BASENAMES = [
        'logo.jpg', 'logo.jpeg', 'logo.png', 'logo.webp',
        'hero.jpg', 'hero.jpeg', 'hero.png', 'hero.webp',
    ];

    /**
     * @return list<string> Логические пути внутри public тенанта: {@code site/brand/hub/01.jpg}, …
     */
    public function importFromSourceDirectory(
        Tenant $tenant,
        string $absoluteSource,
        bool $dryRun,
    ): array {
        $source = rtrim($absoluteSource, DIRECTORY_SEPARATOR);
        if (! is_dir($source) && is_file($source)) {
            $source = dirname($source);
        }
        if (! is_dir($source)) {
            return [];
        }

        $images = $this->collectImageFiles($source);
        if ($images === []) {
            return [];
        }

        if ($dryRun) {
            return array_map(
                static fn (int $i) => self::HUB_LOGICAL.'/'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'.jpg',
                array_keys($images),
            );
        }

        $ts = TenantStorage::forTrusted($tenant);
        $outKeys = [];
        foreach ($images as $idx => $abs) {
            $n = (int) $idx + 1;
            $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) {
                continue;
            }
            $num = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $logical = self::HUB_LOGICAL.'/'.$num.'.'.$ext;
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
                throw new RuntimeException('Не удалось записать: '.$logical);
            }
            $outKeys[] = $logical;
        }

        if ($outKeys === []) {
            return [];
        }

        $this->mergeImageKeysIntoPageSections((int) $tenant->id, $outKeys, count(BlackDuckContentConstants::serviceMatrixQ1()));
        HomeController::forgetCachedPayloadForTenant((int) $tenant->id);

        return $outKeys;
    }

    /**
     * @return list<string> Абсолютные пути к файлам, отсортированные стабильно
     */
    public function collectImageFiles(string $sourceDir): array
    {
        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $files = [];
        /** @var \SplFileInfo $f */
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $base = strtolower($f->getFilename());
            if (in_array($base, self::EXCLUDED_BASENAMES, true)) {
                continue;
            }
            if ($this->isExcludedServiceOrHeroSourceBasename($base)) {
                continue;
            }
            $ext = strtolower($f->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) {
                continue;
            }
            $path = $f->getPathname();
            if (! is_file($path)) {
                continue;
            }
            $files[] = $path;
        }
        sort($files, SORT_STRING);

        return $files;
    }

    private function isExcludedServiceOrHeroSourceBasename(string $baseLower): bool
    {
        foreach (BlackDuckHomeHeroBundle::expectedSourceBasenames() as $b) {
            if ($baseLower === strtolower($b)) {
                return true;
            }
        }
        if (str_starts_with($baseLower, 'blackduck-hero-')) {
            return true;
        }
        if (str_starts_with($baseLower, 'service-landing-hero.')) {
            return true;
        }
        if (str_starts_with($baseLower, 'hero-') && preg_match('/\.(webp|jpg|jpeg)$/i', $baseLower) === 1) {
            return true;
        }
        $named = array_map('strtolower', array_values(BlackDuckServiceImages::sourceBasenameByMatrixSlug()));
        if (in_array($baseLower, $named, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $orderedKeys  Логические ключи (site/brand/hub/01.jpg, …) по порядку
     */
    public function mergeImageKeysIntoPageSections(int $tenantId, array $orderedKeys, ?int $serviceHubSlotCount = null): void
    {
        if ($orderedKeys === []) {
            return;
        }
        $hubSlots = $serviceHubSlotCount ?? count(BlackDuckContentConstants::serviceMatrixQ1());
        if ($hubSlots < 1) {
            $hubSlots = 1;
        }
        $pick = static function (int $i) use ($orderedKeys): string {
            $n = count($orderedKeys);

            return $orderedKeys[$i % $n];
        };

        $this->mergeServiceHub(
            $tenantId,
            'home',
            'service_hub',
            $hubSlots,
            $pick,
        );
        $this->mergeServiceHub(
            $tenantId,
            'uslugi',
            'service_hub',
            $hubSlots,
            $pick,
        );
        for ($c = 0; $c < 3; $c++) {
            $this->mergeCaseItem($tenantId, 'home', 'case_cards', $c, $pick(10 + $c));
        }
        for ($c = 0; $c < 3; $c++) {
            $this->mergeCaseItem($tenantId, 'raboty', 'case_list', $c, $pick(10 + $c));
        }
        $this->mergeBeforeAfter(
            $tenantId,
            'home',
            'before_after',
            $pick(13),
            $pick(14),
        );
    }

    /**
     * @param  \Closure(int): string  $pick
     */
    private function mergeServiceHub(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        int $itemCount,
        \Closure $pick,
    ): void {
        $row = $this->findSection($tenantId, $pageSlug, $sectionKey);
        if ($row === null) {
            return;
        }
        $data = $this->decodeDataJson($row->data_json);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        for ($i = 0; $i < $itemCount; $i++) {
            if (! isset($items[$i]) || ! is_array($items[$i])) {
                $items[$i] = $items[$i] ?? [];
            }
            if (! is_array($items[$i])) {
                $items[$i] = [];
            }
            $items[$i]['image_url'] = $pick($i);
        }
        $data['items'] = $items;
        $this->updateSectionById((int) $row->id, $data);
    }

    private function mergeCaseItem(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        int $index,
        string $logicalKey,
    ): void {
        $row = $this->findSection($tenantId, $pageSlug, $sectionKey);
        if ($row === null) {
            return;
        }
        $data = $this->decodeDataJson($row->data_json);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (! isset($items[$index]) || ! is_array($items[$index])) {
            $items[$index] = [
                'vehicle' => '',
                'task' => '',
                'result' => '',
                'duration' => '',
            ];
        }
        $items[$index]['image_url'] = $logicalKey;
        $data['items'] = $items;
        $this->updateSectionById((int) $row->id, $data);
    }

    private function mergeBeforeAfter(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        string $beforeKey,
        string $afterKey,
    ): void {
        $row = $this->findSection($tenantId, $pageSlug, $sectionKey);
        if ($row === null) {
            return;
        }
        $data = $this->decodeDataJson($row->data_json);
        $data['pairs'] = [
            [
                'before_url' => $beforeKey,
                'after_url' => $afterKey,
                'caption' => 'Кузов и зоны подготовки: до и после этапа работ.',
            ],
        ];
        $this->updateSectionById((int) $row->id, $data);
    }

    private function findSection(int $tenantId, string $pageSlug, string $sectionKey): ?object
    {
        $pageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', $pageSlug)->value('id');
        if ($pageId < 1) {
            return null;
        }

        $row = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->where('page_id', $pageId)
            ->where('section_key', $sectionKey)
            ->first();
        if ($row === null) {
            return null;
        }
        if (! isset($row->id, $row->data_json)) {
            return null;
        }

        return $row;
    }

    private function updateSectionById(int $id, array $data): void
    {
        $enc = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($enc === false) {
            return;
        }
        try {
            DB::table('page_sections')
                ->where('id', $id)
                ->update([
                    'data_json' => $enc,
                    'updated_at' => now(),
                ]);
        } catch (Throwable) {
            // no-op: не падаем при CLI
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDataJson(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) {
                return $d;
            }
        }

        return [];
    }

    /**
     * Именованные картинки из папки «Услуги» → {@code site/brand/services/{key}.ext}, затем обновление hub и hero посадочных.
     *
     * @return array<string, string> slug матрицы (в т.ч. {@code #expert-inquiry}) → логический путь
     */
    public function importServiceImagesFromDirectory(Tenant $tenant, string $absoluteDir): array
    {
        $dir = rtrim($absoluteDir, DIRECTORY_SEPARATOR);
        if (! is_dir($dir)) {
            return [];
        }

        $ts = TenantStorage::forTrusted($tenant);
        $map = BlackDuckServiceImages::sourceBasenameByMatrixSlug();
        $out = [];
        foreach ($map as $matrixSlug => $basename) {
            $candidates = BlackDuckServiceImages::sourceBasenameCandidatesForMatrixSlug($matrixSlug);
            if ($candidates === []) {
                $candidates = [$basename];
            }
            $abs = null;
            foreach ($candidates as $cand) {
                $abs = $this->findFileCaseInsensitiveInDirectory($dir, $cand);
                if ($abs !== null && is_readable($abs)) {
                    break;
                }
            }
            if ($abs === null || ! is_readable($abs)) {
                continue;
            }
            $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) {
                continue;
            }
            $key = BlackDuckServiceImages::storageKeyForMatrixSlug($matrixSlug);
            $logical = BlackDuckServiceImages::PUBLIC_PREFIX.'/'.$key.'.'.$ext;
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
                throw new RuntimeException('Не удалось записать: '.$logical);
            }
            $out[$matrixSlug] = $logical;
        }

        if ($out !== []) {
            $this->applyServiceImagesToPageSections((int) $tenant->id, $out);
            HomeController::forgetCachedPayloadForTenant((int) $tenant->id);
        }

        return $out;
    }

    private function findFileCaseInsensitiveInDirectory(string $dir, string $basename): ?string
    {
        $direct = $dir.DIRECTORY_SEPARATOR.$basename;
        if (is_file($direct)) {
            return $direct;
        }
        $lower = strtolower($basename);
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            if (strtolower((string) $f) === $lower && is_file($dir.DIRECTORY_SEPARATOR.$f)) {
                return $dir.DIRECTORY_SEPARATOR.$f;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $slugToLogical
     */
    private function applyServiceImagesToPageSections(int $tenantId, array $slugToLogical): void
    {
        $this->mergeServiceHubImagesBySlug($tenantId, 'home', 'service_hub', $slugToLogical);
        $this->mergeServiceHubImagesBySlug($tenantId, 'uslugi', 'service_hub', $slugToLogical);
        $skipPerPageHero = BlackDuckServiceImages::firstServiceLandingShadePath($tenantId) !== null;
        foreach ($slugToLogical as $matrixSlug => $path) {
            if (str_starts_with($matrixSlug, '#')) {
                continue;
            }
            if ($skipPerPageHero) {
                continue;
            }
            $pageSlug = BlackDuckServiceImages::storageKeyForMatrixSlug($matrixSlug);
            $this->mergeHeroBackground($tenantId, $pageSlug, $path);
        }
    }

    /**
     * @param  array<string, string>  $slugToLogical
     */
    private function mergeServiceHubImagesBySlug(
        int $tenantId,
        string $pageSlug,
        string $sectionKey,
        array $slugToLogical,
    ): void {
        $row = $this->findSection($tenantId, $pageSlug, $sectionKey);
        if ($row === null) {
            return;
        }
        $data = $this->decodeDataJson($row->data_json);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        foreach ($items as $i => $it) {
            if (! is_array($it)) {
                continue;
            }
            $slug = $this->matrixSlugFromHubItemCta($it);
            if ($slug !== null && isset($slugToLogical[$slug])) {
                $items[$i]['image_url'] = $slugToLogical[$slug];
            }
        }
        $data['items'] = $items;
        $this->updateSectionById((int) $row->id, $data);
    }

    /**
     * @return ?string Slug в терминах {@see BlackDuckContentConstants::serviceMatrixQ1()} (например {@code detejling-mojka} или {@code #expert-inquiry})
     */
    private function matrixSlugFromHubItemCta(array $it): ?string
    {
        $cta = trim((string) ($it['cta_url'] ?? ''));
        if ($cta === '') {
            return null;
        }
        if (str_starts_with($cta, '/')) {
            $s = trim($cta, '/');

            return $s === '' ? null : $s;
        }
        if (str_starts_with($cta, '#')) {
            return $cta;
        }

        return null;
    }

    private function mergeHeroBackground(int $tenantId, string $pageSlug, string $logicalPath): void
    {
        $row = $this->findSection($tenantId, $pageSlug, 'hero');
        if ($row === null) {
            return;
        }
        $data = $this->decodeDataJson($row->data_json);
        $data['background_image'] = $logicalPath;
        $this->updateSectionById((int) $row->id, $data);
    }
}
