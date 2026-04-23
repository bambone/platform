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

        $this->mergeImageKeysIntoPageSections((int) $tenant->id, $outKeys);
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

    /**
     * @param  list<string>  $orderedKeys  Логические ключи (site/brand/hub/01.jpg, …) по порядку
     */
    public function mergeImageKeysIntoPageSections(int $tenantId, array $orderedKeys): void
    {
        if ($orderedKeys === []) {
            return;
        }
        $pick = static function (int $i) use ($orderedKeys): string {
            $n = count($orderedKeys);

            return $orderedKeys[$i % $n];
        };

        $this->mergeServiceHub(
            $tenantId,
            'home',
            'service_hub',
            10,
            $pick,
        );
        $this->mergeServiceHub(
            $tenantId,
            'uslugi',
            'service_hub',
            8,
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
}
