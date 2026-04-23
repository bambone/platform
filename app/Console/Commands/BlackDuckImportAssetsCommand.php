<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Импорт логотипа (и при необходимости hero) в публичное хранилище тенанта. Источник: --source=путь
 * (файл или каталог) или {@code docs/tenants_tz/BlackDuck/assets/} с файлом {@code logo.jpg}.
 */
final class BlackDuckImportAssetsCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:import-assets
                            {tenant=blackduck : slug или id}
                            {--source= : Путь к logo.jpg, или к каталогу (ищем logo.jpg рекурсивно)}
                            {--dry-run : Показать найденный файл без копирования}';

    protected $description = 'Black Duck: скопировать logo.jpg в site/brand/ и проставить branding.logo_path';

    public function handle(BlackDuckContentRefresher $refresher): int
    {
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

        $raw = trim((string) $this->option('source'));
        if ($raw === '') {
            $raw = base_path('docs/tenants_tz/BlackDuck/assets');
        }
        $raw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if (! is_file($raw) && ! is_dir($raw)) {
            $this->error('Нет пути: '.$raw);
            $this->line('Укажите --source=... к каталогу с logo.jpg или к самому файлу. Оператор: положить logo в docs/tenants_tz/BlackDuck/assets/');

            return self::FAILURE;
        }

        $logo = is_file($raw) ? $raw : $this->findLogoJpegUnder($raw);
        if ($logo === null || ! is_file($logo)) {
            $this->error('Не найден logo.jpg по пути: '.$raw);

            return self::FAILURE;
        }

        $this->info('Источник: '.$logo);
        if ((bool) $this->option('dry-run')) {
            $this->info('[dry-run] Скопировали бы в '.BlackDuckContentConstants::LOGO_LOGICAL);

            return self::SUCCESS;
        }

        $out = $refresher->importBrandLogoFromPath($tenant, $logo, false);
        if ($out === null) {
            $this->error('Не удалось записать файл.');

            return self::FAILURE;
        }

        $this->info('OK: branding.logo_path → '.$out);

        return self::SUCCESS;
    }

    private function findLogoJpegUnder(string $dir): ?string
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $direct = $dir.DIRECTORY_SEPARATOR.'logo.jpg';
        if (is_file($direct)) {
            return $direct;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            if (strtolower($f->getFilename()) === 'logo.jpg') {
                return $f->getPathname();
            }
        }

        return null;
    }
}
