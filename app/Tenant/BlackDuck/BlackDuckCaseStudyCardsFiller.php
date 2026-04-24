<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\Tenant;
use App\Models\TenantMediaAsset;
use App\Models\TenantServiceProgram;
use App\Models\TenantSetting;
use App\Services\CurrentTenantManager;
use App\Services\TenantFiles\TenantFileCatalogService;
use App\Support\Storage\TenantStorage;
use App\Tenant\Expert\ExpertBrandMediaUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Заполнение {@code case_list} / {@code case_study_cards} на странице {@code raboty} из существующих public assets.
 *
 * @see BlackDuckContentConstants::WORKS_PAGE_URL
 */
final class BlackDuckCaseStudyCardsFiller
{
    public const PAGE_SLUG = 'raboty';

    public const SECTION_KEY = 'case_list';

    public const SECTION_TYPE = 'case_study_cards';

    /** Куда класть копии при {@see syncMissingFromSourceDir()} (как в Filament case-study picker). */
    public const CASE_STUDY_UPLOAD_PREFIX = 'site/uploads/page-builder/case-study/';

    /** @var list<string> */
    public const PRIMARY_BASENAMES = [
        'yandex_maps_172.webp',
        'yandex_maps_167.webp',
        'yandex_maps_074.webp',
        'yandex_maps_068.webp',
        'yandex_maps_230.webp',
        'yandex_maps_142.webp',
        'yandex_maps_043.webp',
        'yandex_maps_252.webp',
        'yandex_maps_057.webp',
        'yandex_maps_439.webp',
    ];

    /** @var list<string> */
    public const RESERVE_BASENAMES = [
        'yandex_maps_360.webp',
        'yandex_maps_314.webp',
        'yandex_maps_333.webp',
        'yandex_maps_364.webp',
        'yandex_maps_374.webp',
        'yandex_maps_425.webp',
        'yandex_maps_466.webp',
        'yandex_maps_479.webp',
    ];

    /** @var list<array{vehicle: string, task: string, duration: string, result: string}> */
    private const SLOT_COPY = [
        [
            'vehicle' => 'Тёмный кузов',
            'task' => 'Полировка кузова и визуальное восстановление блеска',
            'duration' => '1–2 дня',
            'result' => 'Более глубокий цвет, ровный глянец и аккуратный after',
        ],
        [
            'vehicle' => 'Светлый кузов',
            'task' => 'Локальная доводка и подготовка ЛКП',
            'duration' => 'от 1 дня',
            'result' => 'Поверхность выглядит ровнее и чище, отражение читается лучше',
        ],
        [
            'vehicle' => 'Передняя оптика',
            'task' => 'Обновление и защита фар',
            'duration' => 'несколько часов',
            'result' => 'Оптика выглядит свежее и аккуратнее',
        ],
        [
            'vehicle' => 'Подкапотное пространство',
            'task' => 'Детейлинг моторного отсека',
            'duration' => 'несколько часов',
            'result' => 'Чистый и ухоженный вид без визуального перегруза',
        ],
        [
            'vehicle' => 'Кожаный салон',
            'task' => 'Очистка и уход за кожаными элементами',
            'duration' => '1 день',
            'result' => 'Салон выглядит свежее, кожа чище и аккуратнее',
        ],
        [
            'vehicle' => 'Светлый салон',
            'task' => 'Химчистка интерьера',
            'duration' => '1 день',
            'result' => 'Светлый интерьер возвращён к опрятному виду',
        ],
        [
            'vehicle' => 'Салон автомобиля',
            'task' => 'Подготовка к шумоизоляции',
            'duration' => '1–2 дня',
            'result' => 'Аккуратная разборка и подготовка под укладку материалов',
        ],
        [
            'vehicle' => 'Пол салона',
            'task' => 'Шумоизоляция пола и арок',
            'duration' => '1–3 дня',
            'result' => 'Уложены шумоизоляционные материалы, салон готов к сборке',
        ],
        [
            'vehicle' => 'Чёрный седан',
            'task' => 'Комплексное обновление внешнего вида',
            'duration' => 'по запросу',
            'result' => 'Глубокий блеск кузова и аккуратный showroom-вид',
        ],
        [
            'vehicle' => 'Porsche',
            'task' => 'Финальная подготовка после комплекса работ',
            'duration' => 'по запросу',
            'result' => 'Яркий цвет, чистый кузов и выразительный after',
        ],
    ];

    public function __construct(
        private readonly TenantFileCatalogService $fileCatalog,
        private readonly CurrentTenantManager $tenantManager,
    ) {}

    /**
     * @return array{
     *     skipped: bool,
     *     reason?: string,
     *     tenant_id?: int,
     *     page_id?: int,
     *     section_id?: int|null,
     *     dry_run?: bool,
     *     backup_json?: string|null,
     *     items?: list<array<string, string>>,
     *     mapping?: list<array{basename: string, object_key: string, public_url: string|null, slot: int}>,
     *     excluded_primaries?: list<array{slot: int, basename: string, reason: string}>,
     *     errors?: list<string>
     * }
     */
    public function run(
        Tenant $tenant,
        string $sourceImagesDir,
        bool $dryRun,
        bool $force,
        bool $syncMissingFromSource = false,
    ): array {
        $errors = [];
        $tid = (int) $tenant->id;

        if ($tenant->theme_key !== BlackDuckContentConstants::THEME_KEY) {
            return ['skipped' => true, 'reason' => 'Тема тенанта не black_duck.', 'errors' => []];
        }

        $sourceImagesDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceImagesDir), DIRECTORY_SEPARATOR);
        if ($sourceImagesDir === '' || ! is_dir($sourceImagesDir)) {
            return [
                'skipped' => true,
                'reason' => 'SOURCE_IMAGES_DIR не существует: '.$sourceImagesDir,
                'errors' => [],
            ];
        }

        $pageId = (int) DB::table('pages')
            ->where('tenant_id', $tid)
            ->where('slug', self::PAGE_SLUG)
            ->value('id');
        if ($pageId < 1) {
            return ['skipped' => true, 'reason' => 'Страница '.self::PAGE_SLUG.' не найдена.', 'errors' => []];
        }

        $sectionRow = DB::table('page_sections')
            ->where('tenant_id', $tid)
            ->where('page_id', $pageId)
            ->where('section_key', self::SECTION_KEY)
            ->first();

        if ($sectionRow !== null) {
            $type = (string) ($sectionRow->section_type ?? '');
            if ($type !== self::SECTION_TYPE) {
                return [
                    'skipped' => true,
                    'reason' => 'Секция '.self::SECTION_KEY.' имеет section_type='.$type.', ожидался '.self::SECTION_TYPE.'.',
                    'errors' => [],
                ];
            }
        }

        $targetSectionId = $sectionRow !== null ? (int) $sectionRow->id : null;

        if ($syncMissingFromSource) {
            $this->syncMissingFromSourceDir($tid, $sourceImagesDir);
        }

        $catalogRows = $this->fileCatalog->listLightForTenant($tid, TenantFileCatalogService::FILTER_IMAGES);
        /** @var array<string, list<array{path: string, name: string, path_under_zone: string, segment: string, public_url: string|null}>> $byBase */
        $byBase = [];
        foreach ($catalogRows as $row) {
            $b = strtolower((string) ($row['name'] ?? ''));
            if ($b === '') {
                continue;
            }
            $byBase[$b] ??= [];
            $byBase[$b][] = $row;
        }

        $exclusion = $this->buildExclusionSet($tid, $targetSectionId);
        $existingData = $sectionRow !== null ? $this->decodeDataJson($sectionRow->data_json ?? null) : [];

        $this->tenantManager->setTenant($tenant);
        try {
            if (! $force && $this->countResolvableCaseItems($existingData) >= 10) {
                return [
                    'skipped' => true,
                    'reason' => 'Уже есть ≥10 кейсов с валидным image_url (используйте --force).',
                    'tenant_id' => $tid,
                    'page_id' => $pageId,
                    'section_id' => $targetSectionId,
                    'errors' => [],
                ];
            }

            $reserveAvailable = self::RESERVE_BASENAMES;
            $excludedPrimaries = [];
            $mapping = [];
            $items = [];

            for ($slot = 0; $slot < 10; $slot++) {
                $primaryNorm = self::PRIMARY_BASENAMES[$slot];
                $primaryLower = strtolower($primaryNorm);
                $attempts = array_values(array_unique(array_merge(
                    [$primaryNorm],
                    $reserveAvailable,
                )));
                $chosen = null;
                $chosenBase = null;
                $chosenObjectKey = null;
                $lastReason = '';

                foreach ($attempts as $baseNorm) {
                    $baseLower = strtolower($baseNorm);
                    $localFile = $sourceImagesDir.DIRECTORY_SEPARATOR.$baseNorm;
                    if (! is_file($localFile)) {
                        $lastReason = 'нет файла в SOURCE_IMAGES_DIR';

                        continue;
                    }

                    $rows = $byBase[$baseLower] ?? [];
                    if ($rows === []) {
                        $lastReason = 'нет в tenant public catalog — синхронизируйте storage';

                        continue;
                    }

                    $best = $this->pickBestCatalogRow($rows);
                    $objectKey = $this->objectKeyFromCatalogPath((string) $best['path']);
                    if ($objectKey === null) {
                        $lastReason = 'не удалось разобрать object key';

                        continue;
                    }

                    if ($this->isExcluded($exclusion, $objectKey, $baseLower)) {
                        $lastReason = 'в exclusion (уже используется в другом контенте)';
                        if ($baseLower === $primaryLower) {
                            $excludedPrimaries[] = [
                                'slot' => $slot + 1,
                                'basename' => $primaryNorm,
                                'reason' => $lastReason,
                            ];
                        }

                        continue;
                    }

                    $chosen = $best;
                    $chosenBase = $baseNorm;
                    $chosenObjectKey = $objectKey;
                    break;
                }

                if ($chosen === null || $chosenBase === null || $chosenObjectKey === null) {
                    $errors[] = 'Слот '.($slot + 1).': не удалось подобрать изображение (последняя причина: '.$lastReason.').';

                    continue;
                }

                if (strtolower($chosenBase) !== $primaryLower) {
                    $excludedPrimaries[] = [
                        'slot' => $slot + 1,
                        'basename' => $primaryNorm,
                        'reason' => 'замена на резерв: '.$chosenBase,
                    ];
                }

                $reserveAvailable = array_values(array_filter(
                    $reserveAvailable,
                    static fn (string $r): bool => strtolower($r) !== strtolower($chosenBase),
                ));

                $copy = self::SLOT_COPY[$slot];
                $items[] = [
                    'vehicle' => $copy['vehicle'],
                    'task' => $copy['task'],
                    'duration' => $copy['duration'],
                    'result' => $copy['result'],
                    'image_url' => $chosenObjectKey,
                ];
                $mapping[] = [
                    'basename' => $chosenBase,
                    'object_key' => $chosenObjectKey,
                    'public_url' => $chosen['public_url'] ?? null,
                    'slot' => $slot + 1,
                ];
            }
        } finally {
            $this->tenantManager->clear();
        }

        if (count($items) < 10) {
            return [
                'skipped' => true,
                'reason' => 'Собрано только '.count($items).'/10 элементов.',
                'tenant_id' => $tid,
                'page_id' => $pageId,
                'section_id' => $targetSectionId,
                'errors' => $errors,
                'excluded_primaries' => $excludedPrimaries,
            ];
        }

        $mergedData = is_array($existingData) ? $existingData : [];
        $mergedData['items'] = $items;
        if (! isset($mergedData['heading']) || trim((string) $mergedData['heading']) === '') {
            $mergedData['heading'] = 'Примеры работ';
        }

        $backupJson = $sectionRow !== null
            ? (is_string($sectionRow->data_json) ? $sectionRow->data_json : json_encode($sectionRow->data_json, JSON_UNESCAPED_UNICODE))
            : null;

        if ($dryRun) {
            return [
                'skipped' => false,
                'dry_run' => true,
                'tenant_id' => $tid,
                'page_id' => $pageId,
                'section_id' => $targetSectionId,
                'backup_json' => $backupJson,
                'items' => $items,
                'mapping' => $mapping,
                'excluded_primaries' => $excludedPrimaries,
                'errors' => $errors,
            ];
        }

        $encoded = json_encode($mergedData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            DB::transaction(function () use ($tid, $pageId, $targetSectionId, $encoded, $backupJson): void {
                if ($backupJson !== null) {
                    Log::info('black_duck_case_study_cards_backup', [
                        'tenant_id' => $tid,
                        'page_id' => $pageId,
                        'section_id' => $targetSectionId,
                        'data_json_before' => $backupJson,
                    ]);
                }

                if ($targetSectionId !== null) {
                    DB::table('page_sections')->where('id', $targetSectionId)->update([
                        'data_json' => $encoded,
                        'is_visible' => true,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('page_sections')->insert([
                        'tenant_id' => $tid,
                        'page_id' => $pageId,
                        'section_key' => self::SECTION_KEY,
                        'section_type' => self::SECTION_TYPE,
                        'title' => 'Кейсы',
                        'data_json' => $encoded,
                        'sort_order' => 20,
                        'is_visible' => true,
                        'status' => 'published',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $e) {
            return [
                'skipped' => true,
                'reason' => 'Ошибка БД: '.$e->getMessage(),
                'tenant_id' => $tid,
                'page_id' => $pageId,
                'section_id' => $targetSectionId,
                'errors' => array_merge($errors, [$e->getMessage()]),
            ];
        }

        $this->tenantManager->setTenant($tenant);
        try {
            $verifyErrors = [];
            foreach ($items as $idx => $it) {
                $url = ExpertBrandMediaUrl::resolve((string) ($it['image_url'] ?? ''));
                if ($url === '') {
                    $verifyErrors[] = 'После записи слот '.($idx + 1).': resolve пустой для '.($it['image_url'] ?? '');
                }
            }
        } finally {
            $this->tenantManager->clear();
        }

        return [
            'skipped' => false,
            'dry_run' => false,
            'tenant_id' => $tid,
            'page_id' => $pageId,
            'section_id' => $targetSectionId ?? (int) DB::table('page_sections')
                ->where('tenant_id', $tid)
                ->where('page_id', $pageId)
                ->where('section_key', self::SECTION_KEY)
                ->value('id'),
            'backup_json' => $backupJson,
            'items' => $items,
            'mapping' => $mapping,
            'excluded_primaries' => $excludedPrimaries,
            'errors' => array_merge($errors, $verifyErrors),
        ];
    }

    /**
     * Копирует в tenant public только отсутствующие файлы (по имени), без перезаписи существующих.
     */
    private function syncMissingFromSourceDir(int $tenantId, string $sourceImagesDir): void
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $names = array_values(array_unique(array_merge(self::PRIMARY_BASENAMES, self::RESERVE_BASENAMES)));
        foreach ($names as $basename) {
            $local = $sourceImagesDir.DIRECTORY_SEPARATOR.$basename;
            if (! is_file($local)) {
                continue;
            }
            $logical = self::CASE_STUDY_UPLOAD_PREFIX.$basename;
            if ($ts->existsPublic($logical)) {
                continue;
            }
            $raw = file_get_contents($local);
            if ($raw === false || $raw === '') {
                continue;
            }
            $lower = strtolower($basename);
            $mime = str_ends_with($lower, '.webp')
                ? 'image/webp'
                : (str_ends_with($lower, '.png') ? 'image/png' : 'image/jpeg');
            $ts->putPublic($logical, $raw, ['ContentType' => $mime, 'visibility' => 'public']);
        }
    }

    /**
     * @param  array<string, true>  $exclusionBasenames
     * @param  array<string, true>  $exclusionObjectKeys
     */
    private function isExcluded(array $exclusion, string $objectKey, string $basenameLower): bool
    {
        $ok = strtolower(str_replace('\\', '/', $objectKey));
        if (isset($exclusion['object_keys'][$ok])) {
            return true;
        }
        if (isset($exclusion['basenames'][$basenameLower])) {
            return true;
        }

        return false;
    }

    /**
     * @return array{basenames: array<string, true>, object_keys: array<string, true>}
     */
    private function buildExclusionSet(int $tenantId, ?int $targetSectionId): array
    {
        $basenames = [];
        $objectKeys = [];

        $addString = function (string $s) use (&$basenames, &$objectKeys): void {
            $s = trim($s);
            if ($s === '') {
                return;
            }
            foreach ($this->stringsFromBlob($s) as $fragment) {
                $this->ingestAssetFragment($fragment, $basenames, $objectKeys);
            }
        };

        $sections = DB::table('page_sections')
            ->where('tenant_id', $tenantId)
            ->when($targetSectionId !== null, fn ($q) => $q->where('id', '!=', $targetSectionId))
            ->get(['id', 'data_json']);

        foreach ($sections as $sec) {
            $addString((string) json_encode($sec->data_json, JSON_UNESCAPED_UNICODE));
        }

        $settings = TenantSetting::query()->where('tenant_id', $tenantId)->get(['value']);
        foreach ($settings as $st) {
            $addString((string) $st->value);
        }

        $programs = TenantServiceProgram::query()
            ->where('tenant_id', $tenantId)
            ->get([
                'teaser', 'description', 'audience_json', 'catalog_meta_json',
                'cover_presentation_json', 'cover_image_ref', 'cover_mobile_ref',
            ]);
        foreach ($programs as $p) {
            foreach ([
                'teaser', 'description', 'audience_json', 'catalog_meta_json',
                'cover_presentation_json', 'cover_image_ref', 'cover_mobile_ref',
            ] as $col) {
                $v = $p->{$col} ?? null;
                if (is_array($v) || is_object($v)) {
                    $addString((string) json_encode($v, JSON_UNESCAPED_UNICODE));
                } else {
                    $addString((string) $v);
                }
            }
        }

        $assets = TenantMediaAsset::query()
            ->where('tenant_id', $tenantId)
            ->get(['logical_path', 'poster_logical_path', 'derivatives_json']);
        foreach ($assets as $a) {
            $addString((string) $a->logical_path);
            $addString((string) $a->poster_logical_path);
            $dj = $a->derivatives_json;
            if (is_array($dj)) {
                $addString((string) json_encode($dj, JSON_UNESCAPED_UNICODE));
            }
        }

        return ['basenames' => $basenames, 'object_keys' => $objectKeys];
    }

    /**
     * @param  array<string, true>  $basenames
     * @param  array<string, true>  $objectKeys
     */
    private function ingestAssetFragment(string $fragment, array &$basenames, array &$objectKeys): void
    {
        $fragment = str_replace('\\/', '/', $fragment);

        if (preg_match_all('#tenants/\d+/public/([^"\'\s>]+)#i', $fragment, $m)) {
            foreach ($m[1] as $rel) {
                $rel = strtolower(rtrim(str_replace('\\', '/', $rel), '/'));
                if ($rel !== '') {
                    $objectKeys[$rel] = true;
                    $basenames[strtolower(basename($rel))] = true;
                }
            }
        }

        if (preg_match_all('#\b(site/[a-z0-9_./\-]+\.(?:webp|jpe?g|png|gif|avif|mp4|webm))\b#i', $fragment, $m)) {
            foreach ($m[1] as $rel) {
                $rel = strtolower(str_replace('\\', '/', $rel));
                $objectKeys[$rel] = true;
                $basenames[strtolower(basename($rel))] = true;
            }
        }

        if (preg_match_all('#\b(storage/media/tenants/\d+/public/[^"\'\s>]+)#i', $fragment, $m)) {
            foreach ($m[1] as $p) {
                $addString = str_replace('\\', '/', strtolower($p));
                if (preg_match('#/public/(.+)$#', $addString, $mm)) {
                    $objectKeys[$mm[1]] = true;
                    $basenames[strtolower(basename($mm[1]))] = true;
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function stringsFromBlob(string $blob): array
    {
        $out = [$blob];
        $decoded = json_decode($blob, true);
        if (is_array($decoded)) {
            $stack = [$decoded];
            while ($stack !== []) {
                $cur = array_pop($stack);
                if (is_array($cur)) {
                    foreach ($cur as $v) {
                        if (is_string($v)) {
                            $out[] = $v;
                        } elseif (is_array($v)) {
                            $stack[] = $v;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array{path: string, name: string, path_under_zone: string, segment: string, public_url: string|null}>  $rows
     * @return array{path: string, name: string, path_under_zone: string, segment: string, public_url: string|null}
     */
    private function pickBestCatalogRow(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $uza = (string) ($a['path_under_zone'] ?? '');
            $uzb = (string) ($b['path_under_zone'] ?? '');
            $sa = (str_contains($uza, 'case-study') ? 0 : 1) * 100
                + (str_contains($uza, 'uploads') ? 0 : 1) * 10
                + (str_contains($uza, 'page-builder') ? 0 : 1);
            $sb = (str_contains($uzb, 'case-study') ? 0 : 1) * 100
                + (str_contains($uzb, 'uploads') ? 0 : 1) * 10
                + (str_contains($uzb, 'page-builder') ? 0 : 1);

            return $sa <=> $sb ?: strcmp((string) $a['path'], (string) $b['path']);
        });

        return $rows[0];
    }

    private function objectKeyFromCatalogPath(string $diskPath): ?string
    {
        if (preg_match('#^tenants/\d+/public/(.+)$#', str_replace('\\', '/', $diskPath), $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function decodeDataJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);

            return is_array($d) ? $d : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function countResolvableCaseItems(array $data): int
    {
        $items = $data['items'] ?? [];
        if (! is_array($items)) {
            return 0;
        }
        $n = 0;
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $p = trim((string) ($it['image_url'] ?? ''));
            if ($p === '') {
                continue;
            }
            if (ExpertBrandMediaUrl::resolve($p) !== '') {
                $n++;
            }
        }

        return $n;
    }
}
