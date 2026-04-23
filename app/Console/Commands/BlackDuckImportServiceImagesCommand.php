<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\BlackDuck\BlackDuckDuckMediaImporter;
use App\Tenant\BlackDuck\BlackDuckServiceImages;
use Illuminate\Console\Command;

/**
 * Именованные jpg из папки «Услуги» → {@code site/brand/services/}, hub + фон hero посадочных.
 */
final class BlackDuckImportServiceImagesCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:import-service-images
                            {tenant=blackduck : slug или id}
                            {--source= : Каталог с файлами (имена как в BlackDuckServiceImages)}
                            {--dry-run : Только отчёт}';

    protected $description = 'Black Duck: импорт jpg в site/brand/services (запись через TenantStorage, при dual — сразу в R2) и привязка к hub/hero';

    public function handle(
        BlackDuckDuckMediaImporter $importer,
        BlackDuckContentRefresher $refresher,
    ): int {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
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
            if (DIRECTORY_SEPARATOR === '\\') {
                $raw = 'C:\Users\g-man\Desktop\duck\Услуги';
            } else {
                $this->error('Укажите --source=путь к папке «Услуги» с jpg.');

                return self::FAILURE;
            }
        }
        $raw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if (! is_dir($raw)) {
            $this->error('Нет каталога: '.$raw);

            return self::FAILURE;
        }

        $expected = BlackDuckServiceImages::sourceBasenameByMatrixSlug();
        $this->info('Ожидаемые файлы: '.count($expected).'.');
        if ((bool) $this->option('dry-run')) {
            foreach ($expected as $slug => $base) {
                $cands = BlackDuckServiceImages::sourceBasenameCandidatesForMatrixSlug($slug);
                if ($cands === []) {
                    $cands = [$base];
                }
                $found = null;
                foreach ($cands as $c) {
                    $p = $raw.DIRECTORY_SEPARATOR.$c;
                    if (is_file($p)) {
                        $found = $c;
                        break;
                    }
                }
                $ok = $found !== null ? 'OK' : '—';
                $this->line("  [{$ok}] ".($found ?? implode('|', $cands))."  →  {$slug}");
            }
            $this->info('[dry-run] Файлы в storage не пишем.');

            return self::SUCCESS;
        }

        $out = $importer->importServiceImagesFromDirectory($tenant, $raw);
        if ($out === []) {
            $this->error('Не скопировано ни одного файла: проверьте имена (см. app/Tenant/BlackDuck/BlackDuckServiceImages).');

            return self::FAILURE;
        }
        $this->info('Скопировано: '.count($out).' — '.implode(', ', array_values($out)));

        return self::SUCCESS;
    }
}
