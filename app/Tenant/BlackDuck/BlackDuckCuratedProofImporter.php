<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use RuntimeException;

/**
 * Импорт curated-export: файлы в {@code site/brand/proof/}, манифест {@see BlackDuckMediaCatalog::CATALOG_LOGICAL}.
 */
final class BlackDuckCuratedProofImporter
{
    private const PROOF_PREFIX = 'site/brand/proof';

    /**
     * @return array{imported_files: list<string>, catalog_assets: int}
     */
    public function import(
        Tenant $tenant,
        string $absoluteSourceDir,
        ?string $manifestPath,
        bool $dryRun,
        bool $force,
    ): array {
        $dir = rtrim($absoluteSourceDir, DIRECTORY_SEPARATOR);
        if (! is_dir($dir)) {
            throw new RuntimeException('Каталог не найден: '.$dir);
        }
        $mf = $manifestPath ?? $dir.DIRECTORY_SEPARATOR.'curated-manifest.json';
        if (! is_readable($mf)) {
            throw new RuntimeException('Нет манифеста: '.$mf);
        }
        $json = file_get_contents($mf);
        if (! is_string($json) || $json === '') {
            throw new RuntimeException('Пустой манифест.');
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new RuntimeException('Некорректный JSON манифеста.');
        }
        $rows = $data['assets'] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('В манифесте нет assets[].');
        }

        $tid = (int) $tenant->id;
        $ts = TenantStorage::forTrusted($tenant);
        $catalog = BlackDuckMediaCatalog::loadOrEmpty($tid);
        $incomingNormalized = [];
        $importedFiles = [];
        $idx = 0;

        if ($dryRun) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rel = trim((string) ($row['source'] ?? ''));
                if ($rel === '') {
                    continue;
                }
                $abs = $dir.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
                if (! is_readable($abs)) {
                    continue;
                }
                $probe = BlackDuckMediaCatalog::normalizeAssetRow(array_merge($row, [
                    'logical_path' => 'site/brand/proof/dry-run.jpg',
                ]));
                if ($probe !== null) {
                    $incomingNormalized[] = $probe;
                }
            }

            return [
                'imported_files' => [],
                'catalog_assets' => count($incomingNormalized),
            ];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rel = trim((string) ($row['source'] ?? ''));
            if ($rel === '') {
                continue;
            }
            $abs = $dir.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
            if (! is_readable($abs)) {
                continue;
            }
            $roleRaw = trim((string) ($row['role'] ?? ''));
            $canonicalRole = BlackDuckMediaCatalog::normalizeAssetRow(array_merge($row, [
                'role' => $roleRaw,
                'logical_path' => 'site/brand/proof/placeholder.jpg',
            ]));
            if ($canonicalRole === null) {
                continue;
            }
            $role = $canonicalRole['role'];
            $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
            $isVideo = $ext === 'mp4' || $ext === 'webm';
            $logical = null;
            $bytes = null;
            $contentType = null;

            if ($isVideo) {
                if ($role !== BlackDuckMediaRole::WorksFeaturedVideo->value
                    && $role !== BlackDuckMediaRole::ServiceFeaturedVideo->value) {
                    continue;
                }
                $raw = @file_get_contents($abs);
                if (! is_string($raw) || $raw === '') {
                    continue;
                }
                $bytes = $raw;
                $vidExt = $ext === 'webm' ? 'webm' : 'mp4';
                $contentType = $ext === 'webm' ? 'video/webm' : 'video/mp4';
                $stem = self::safeStem($rel, $idx, $vidExt);
                $logical = self::PROOF_PREFIX.'/'.$stem;
            } else {
                $norm = BlackDuckProofImageNormalizer::normalizeFile($abs);
                if ($norm === null) {
                    continue;
                }
                [$bytes, $contentType] = $norm;
                $outExt = BlackDuckProofImageNormalizer::outputExtensionForContentType($contentType);
                $stem = self::safeStem($rel, $idx, $outExt);
                $logical = self::PROOF_PREFIX.'/'.$stem;
            }

            $posterLogical = null;
            $posterRel = trim((string) ($row['poster_source'] ?? ''));
            if ($posterRel !== '' && ($role === BlackDuckMediaRole::WorksFeaturedVideo->value || $role === BlackDuckMediaRole::ServiceFeaturedVideo->value)) {
                $pAbs = $dir.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $posterRel);
                $pn = BlackDuckProofImageNormalizer::normalizeFile($pAbs);
                if ($pn !== null) {
                    [$pBytes, $pCt] = $pn;
                    $pExt = BlackDuckProofImageNormalizer::outputExtensionForContentType($pCt);
                    $pStem = self::safeStem($posterRel, $idx + 1000, $pExt);
                    $posterLogical = self::PROOF_PREFIX.'/'.$pStem;
                    if (! $ts->putPublic($posterLogical, $pBytes, [
                        'ContentType' => $pCt,
                        'visibility' => 'public',
                    ])) {
                        throw new RuntimeException('Не удалось записать постер: '.$posterLogical);
                    }
                    $importedFiles[] = $posterLogical;
                }
            }

            if (! $force && $ts->existsPublic($logical)) {
                throw new RuntimeException('Файл уже есть (используйте --force): '.$logical);
            }
            if (! $ts->putPublic($logical, $bytes, [
                'ContentType' => $contentType,
                'visibility' => 'public',
            ])) {
                throw new RuntimeException('Не удалось записать: '.$logical);
            }
            $importedFiles[] = $logical;

            $asset = [
                'role' => $role,
                'service_slug' => $canonicalRole['service_slug'],
                'page_slug' => $canonicalRole['page_slug'],
                'sort_order' => (int) ($row['sort_order'] ?? $canonicalRole['sort_order']),
                'is_featured' => (bool) ($row['is_featured'] ?? $canonicalRole['is_featured']),
                'caption' => trim((string) ($row['caption'] ?? $canonicalRole['caption'])),
                'alt' => trim((string) ($row['alt'] ?? $canonicalRole['alt'])),
                'before_after_group' => $canonicalRole['before_after_group'],
                'logical_path' => $logical,
                'poster_logical_path' => $posterLogical ?? ($canonicalRole['poster_logical_path'] ?? null),
                'source_ref' => isset($row['source_ref']) ? trim((string) $row['source_ref']) : $canonicalRole['source_ref'],
                'kind' => $isVideo ? 'video' : 'image',
            ];
            if (($asset['poster_logical_path'] ?? null) === '') {
                $asset['poster_logical_path'] = null;
            }
            $normalized = BlackDuckMediaCatalog::normalizeAssetRow($asset, $tid);
            if ($normalized !== null) {
                $incomingNormalized[] = $normalized;
            }
            $idx++;
        }

        if ($incomingNormalized === []) {
            throw new RuntimeException('Не удалось сформировать ни одной валидной записи каталога.');
        }

        $merged = $force
            ? $incomingNormalized
            : $this->mergeCatalogAssetsKeepUnmatched($catalog['assets'], $incomingNormalized);
        $ok = BlackDuckMediaCatalog::saveCatalog($tid, BlackDuckMediaCatalog::SCHEMA_VERSION, $merged);
        if (! $ok) {
            throw new RuntimeException('Не удалось сохранить media-catalog.json.');
        }

        return ['imported_files' => $importedFiles, 'catalog_assets' => count($incomingNormalized)];
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergeCatalogAssetsKeepUnmatched(array $existing, array $incoming): array
    {
        $drop = [];
        foreach ($incoming as $i) {
            $drop[$this->slotKey($i)] = true;
        }
        $kept = [];
        foreach ($existing as $e) {
            if (! isset($drop[$this->slotKey($e)])) {
                $kept[] = $e;
            }
        }

        return array_merge($kept, $incoming);
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function slotKey(array $a): string
    {
        return implode('|', [
            (string) ($a['role'] ?? ''),
            (string) ($a['service_slug'] ?? ''),
            (string) ($a['page_slug'] ?? ''),
            (string) ($a['before_after_group'] ?? ''),
            (string) (int) ($a['sort_order'] ?? 0),
        ]);
    }

    private static function safeStem(string $relative, int $idx, string $ext): string
    {
        $base = pathinfo($relative, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9._-]+/u', '-', (string) $base) ?? 'asset';
        $base = trim((string) $base, '-');
        if ($base === '') {
            $base = 'asset';
        }
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext) ?? 'jpg');

        return str_pad((string) $idx, 2, '0', STR_PAD_LEFT).'-'.substr($base, 0, 60).'.'.$ext;
    }
}
