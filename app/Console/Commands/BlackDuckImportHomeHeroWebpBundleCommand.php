<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckHomeHeroBundle;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use Illuminate\Console\Command;

/**
 * Импорт responsive hero: {@see BlackDuckHomeHeroBundle::STORAGE_LOGICAL} из каталога с файлами
 * {@code hero-1916.webp}, {@code hero-1600.webp}, {@code hero-900.webp}, {@code hero-900-1200.webp}, {@code hero-1916.jpg}.
 * Старые {@code site/brand/hero.*} и {@code service-landing-hero.*} удаляются, если в источнике найден хотя бы один ожидаемый файл.
 */
final class BlackDuckImportHomeHeroWebpBundleCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:import-home-hero-bundle
                            {tenant=blackduck : slug или id}
                            {--source= : Каталог с WebP+JPEG (см. BlackDuckHomeHeroBundle::STORAGE_LOGICAL)}
                            {--dry-run : Показать, какие роли совпадут, без копирования}';

    protected $description = 'Black Duck: импорт бандла hero (WebP + JPEG) в site/brand/, обновление expert_hero и фона посадок';

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
            $raw = $this->defaultSourceDirectory();
        }
        $raw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if (! is_dir($raw)) {
            $this->error('Нет каталога: '.$raw);
            $this->line('Положите файлы: '.implode(', ', BlackDuckHomeHeroBundle::expectedSourceBasenames()));
            $this->line('Или укажите --source=...');

            return self::FAILURE;
        }

        $this->info('Каталог: '.$raw);
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('[dry-run] копирование и удаление легаси не выполняются');
        }

        $out = $refresher->importHomeHeroWebpBundleFromDirectory($tenant, $raw, $dry);
        if ($out === []) {
            $this->error('Не найдено ни одного ожидаемого файла (имена: '.implode(', ', BlackDuckHomeHeroBundle::expectedSourceBasenames()).').');

            return self::FAILURE;
        }

        foreach ($out as $role => $logical) {
            $this->line('  '.$role.' → '.$logical);
        }
        if ($dry) {
            $this->info('[dry-run] готово (без записи в storage).');
        } else {
            $this->info('OK: обновлены секции, сброшен кэш главной.');
        }

        return self::SUCCESS;
    }

    private function defaultSourceDirectory(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $up = getenv('USERPROFILE') ?: 'C:\\Users\\g-man';

            return rtrim($up, '\\').'\\Desktop\\duck\\Услуги';
        }

        return base_path('docs/tenants_tz/BlackDuck/hero-bundle');
    }
}
