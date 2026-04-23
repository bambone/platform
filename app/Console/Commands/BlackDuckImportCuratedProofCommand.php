<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\BlackDuck\BlackDuckCuratedProofImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Curated-export: файлы клиента → {@code site/brand/proof/} + {@see \App\Tenant\BlackDuck\BlackDuckMediaCatalog}.
 *
 * Манифест по умолчанию: {@code curated-manifest.json} в корне --source.
 */
final class BlackDuckImportCuratedProofCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:import-curated-proof
                            {tenant=blackduck : slug или id}
                            {--source= : Каталог экспорта клиента (с curated-manifest.json)}
                            {--manifest= : Путь к JSON манифесту (иначе source/curated-manifest.json)}
                            {--dry-run : Без записи в хранилище и каталог}
                            {--force : Перезапись файлов при коллизии; каталог = только строки из этого манифеста (полная замена)}';

    protected $description = 'Black Duck: импорт curated proof в site/brand/proof и media-catalog.json';

    public function handle(
        BlackDuckCuratedProofImporter $importer,
        BlackDuckContentRefresher $refresher,
    ): int {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (Throwable) {
            $t = $refresher->resolveBlackDuckTenant();
            if ($t === null) {
                $this->error('Тенант Black Duck не найден.');

                return self::FAILURE;
            }
            $tenant = $t;
        }

        if ($tenant->theme_key !== 'black_duck') {
            $this->error('Указан тенант не black_duck.');

            return self::FAILURE;
        }

        $raw = (string) $this->option('source');
        if (trim($raw) === '') {
            $this->error('Укажите --source=каталог с curated-manifest.json и файлами.');

            return self::FAILURE;
        }
        $raw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if (! is_dir($raw)) {
            $this->error('Нет каталога: '.$raw);

            return self::FAILURE;
        }

        $manifest = $this->option('manifest');
        $manifestPath = is_string($manifest) && trim($manifest) !== ''
            ? str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($manifest))
            : null;

        try {
            $out = $importer->import(
                $tenant,
                $raw,
                $manifestPath,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force'),
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info('[dry-run] Валидных записей каталога: '.$out['catalog_assets'].'. Файлы не записывались.');

            return self::SUCCESS;
        }

        $this->info('Импортировано файлов: '.count($out['imported_files']).'; записей в каталоге: '.$out['catalog_assets'].'.');
        $this->warn('Выполните tenant:black-duck:refresh-content --force для синхронизации секций.');

        return self::SUCCESS;
    }
}
