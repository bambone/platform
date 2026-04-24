<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Models\Tenant;
use App\Models\TenantMediaAsset;
use App\Support\Storage\TenantStorage;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Импортирует `site/brand/media-catalog.json` (curated proof) в БД, чтобы каталог редактировался из админки.
 * Файлы (бинарники) остаются в public storage тенанта.
 */
final class BlackDuckImportMediaCatalogToDbCommand extends Command
{
    use ResolvesTenantArgument;

    /**
     * @param  array<string, mixed>  $r
     */
    private static function nullableBoolFromNormalizedRow(array $r, string $key): ?bool
    {
        if (! array_key_exists($key, $r)) {
            return null;
        }

        return BlackDuckMediaCatalog::optionalBoolFromRowValue($r[$key]);
    }

    protected $signature = 'tenant:black-duck:import-media-catalog-to-db
                            {tenant=blackduck : slug или id}
                            {--wipe : Удалить существующие записи tenant_media_assets перед импортом}
                            {--only-missing : Не обновлять существующие записи (только создать отсутствующие по catalog_key)}
                            {--refresh : После импорта выполнить tenant:black-duck:refresh-content --force}';

    protected $description = 'Black Duck: импортировать site/brand/media-catalog.json в БД (tenant_media_assets)';

    public function handle(): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
            $this->error('Тенант не найден по slug/id: '.$key);

            return self::FAILURE;
        }

        if ((string) $tenant->theme_key !== BlackDuckContentConstants::THEME_KEY) {
            $this->error('Указан тенант не black_duck.');

            return self::FAILURE;
        }

        $tenantId = (int) $tenant->id;
        $ts = TenantStorage::forTrusted($tenantId);
        if (! $ts->existsPublic(BlackDuckMediaCatalog::CATALOG_LOGICAL)) {
            $this->error('Не найден файл в public storage: '.BlackDuckMediaCatalog::CATALOG_LOGICAL);

            return self::FAILURE;
        }
        $raw = $ts->getPublic(BlackDuckMediaCatalog::CATALOG_LOGICAL);
        if (! is_string($raw) || trim($raw) === '') {
            $this->error('Файл каталога пуст или не читается.');

            return self::FAILURE;
        }
        $json = json_decode($raw, true);
        if (! is_array($json) || ! is_array($json['assets'] ?? null)) {
            $this->error('Некорректный JSON: ожидается объект с assets[].');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($json['assets'] as $a) {
            if (! is_array($a)) {
                continue;
            }
            $row = BlackDuckMediaCatalog::normalizeAssetRow($a, $tenantId);
            if ($row === null) {
                continue;
            }
            $rows[] = $row;
        }

        if ($rows === []) {
            $this->warn('Не найдено ни одной валидной записи для импорта.');

            return self::SUCCESS;
        }

        $onlyMissing = (bool) $this->option('only-missing');

        DB::transaction(function () use ($tenantId, $rows, $onlyMissing): void {
            if ((bool) $this->option('wipe')) {
                TenantMediaAsset::query()->where('tenant_id', $tenantId)->delete();
            }

            foreach ($rows as $r) {
                $catalogKey = TenantMediaAsset::catalogKeyFor(
                    (string) ($r['role'] ?? ''),
                    (string) ($r['logical_path'] ?? ''),
                    (string) ($r['poster_logical_path'] ?? ''),
                    (string) ($r['service_slug'] ?? ''),
                    (string) ($r['page_slug'] ?? ''),
                    (string) ($r['before_after_group'] ?? ''),
                    (string) ($r['works_group'] ?? ''),
                );

                if ($onlyMissing && TenantMediaAsset::query()
                    ->where('tenant_id', $tenantId)
                    ->where('catalog_key', $catalogKey)
                    ->exists()) {
                    continue;
                }

                TenantMediaAsset::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'catalog_key' => $catalogKey],
                    [
                        'role' => (string) ($r['role'] ?? ''),
                        'logical_path' => (string) ($r['logical_path'] ?? ''),
                        'poster_logical_path' => isset($r['poster_logical_path']) && $r['poster_logical_path'] !== '' ? (string) $r['poster_logical_path'] : null,
                        'service_slug' => isset($r['service_slug']) && $r['service_slug'] !== '' ? (string) $r['service_slug'] : null,
                        'page_slug' => isset($r['page_slug']) && $r['page_slug'] !== '' ? (string) $r['page_slug'] : null,
                        'before_after_group' => isset($r['before_after_group']) && $r['before_after_group'] !== '' ? (string) $r['before_after_group'] : null,
                        'works_group' => isset($r['works_group']) && $r['works_group'] !== '' ? (string) $r['works_group'] : null,
                        'sort_order' => (int) ($r['sort_order'] ?? 0),
                        'is_featured' => (bool) ($r['is_featured'] ?? false),
                        'caption' => isset($r['caption']) && $r['caption'] !== '' ? (string) $r['caption'] : null,
                        'alt' => isset($r['alt']) && $r['alt'] !== '' ? (string) $r['alt'] : null,
                        'kind' => isset($r['kind']) && $r['kind'] !== '' ? (string) $r['kind'] : null,
                        'title' => isset($r['title']) && $r['title'] !== '' ? (string) $r['title'] : null,
                        'summary' => isset($r['summary']) && $r['summary'] !== '' ? (string) $r['summary'] : null,
                        'service_label' => isset($r['service_label']) && $r['service_label'] !== '' ? (string) $r['service_label'] : null,
                        'tags_json' => is_array($r['tags'] ?? null) ? array_values($r['tags']) : null,
                        'aspect_hint' => isset($r['aspect_hint']) && $r['aspect_hint'] !== '' ? (string) $r['aspect_hint'] : null,
                        'display_variant' => isset($r['display_variant']) && $r['display_variant'] !== '' ? (string) $r['display_variant'] : null,
                        'badge' => isset($r['badge']) && $r['badge'] !== '' ? (string) $r['badge'] : null,
                        'cta_label' => isset($r['cta_label']) && $r['cta_label'] !== '' ? (string) $r['cta_label'] : null,
                        'show_on_home' => self::nullableBoolFromNormalizedRow($r, 'show_on_home'),
                        'show_on_works' => self::nullableBoolFromNormalizedRow($r, 'show_on_works'),
                        'show_on_service' => self::nullableBoolFromNormalizedRow($r, 'show_on_service'),
                        'source_ref' => isset($r['source_ref']) && $r['source_ref'] !== '' ? (string) $r['source_ref'] : null,
                        'derivatives_json' => is_array($r['derivatives'] ?? null) ? array_values($r['derivatives']) : null,
                    ],
                );
            }
        });

        $this->info('Импортировано записей: '.count($rows).'.');

        if ((bool) $this->option('refresh')) {
            $this->call(BlackDuckRefreshContentCommand::class, [
                'tenant' => (string) $tenant->slug,
                '--force' => true,
                '--if-placeholder' => '0',
            ]);
        }

        return self::SUCCESS;
    }
}

