<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use App\Tenant\BlackDuck\BlackDuckMediaRole;
use App\Tenant\Expert\ExpertBrandMediaUrl;
use Illuminate\Console\Command;

/**
 * Диагностика: на проде «все картинки как один плейсхолдер» / 404 — чаще всего нет объектов в публичном storage
 * (R2 или зеркало), с которым сконфигурировано приложение. См. docs/tenant/black-duck-media-layout.md.
 */
final class BlackDuckVerifyPublicMediaCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:verify-public-media
                            {tenant=blackduck : slug или id}
                            {--role= : Фильтр роли: works_gallery, service_gallery, all (по умолчанию — все ассеты каталога)}
                            {--strict : Код выхода 1, если есть отсутствующие файлы}';

    protected $description = 'Black Duck: проверить, что logical_path из media-catalog.json есть в публичном storage тенанта';

    public function handle(): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
            $this->error('Тенант не найден по slug/id: '.$key);

            return self::FAILURE;
        }

        if ($tenant->theme_key !== BlackDuckContentConstants::THEME_KEY) {
            $this->error('Тема тенанта не black_duck.');

            return self::FAILURE;
        }

        $tenantId = (int) $tenant->id;
        $cat = BlackDuckMediaCatalog::loadOrEmpty($tenantId);
        if ($cat['assets'] === []) {
            $this->warn('Каталог пуст или нет '.BlackDuckMediaCatalog::CATALOG_LOGICAL.' в public storage тенанта '.$tenantId.'.');
            $this->line('Загрузите media-catalog.json и proof (см. scripts/black-duck/publish-media-tenant4.ps1).');

            return (bool) $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $roleFilter = trim((string) $this->option('role'));
        if ($roleFilter === '') {
            $roleFilter = 'all';
        }

        $paths = [];
        foreach ($cat['assets'] as $a) {
            if (! is_array($a)) {
                continue;
            }
            $role = (string) ($a['role'] ?? '');
            if ($roleFilter !== 'all') {
                if ($role !== $roleFilter) {
                    continue;
                }
            }
            $main = trim((string) ($a['logical_path'] ?? ''));
            if ($main !== '') {
                $paths[$main] = $role;
            }
            $poster = trim((string) ($a['poster_logical_path'] ?? ''));
            if ($poster !== '') {
                $paths[$poster] = $role.' (poster)';
            }
            $deriv = is_array($a['derivatives'] ?? null) ? $a['derivatives'] : [];
            foreach ($deriv as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $p = trim((string) ($d['logical_path'] ?? ''));
                if ($p !== '') {
                    $paths[$p] = $role.' (derivative)';
                }
            }
        }

        if ($paths === []) {
            $this->warn('Нет путей для проверки (роль «'.$roleFilter.'»).');

            return self::SUCCESS;
        }

        $missing = [];
        $ok = 0;
        foreach (array_keys($paths) as $path) {
            if (BlackDuckMediaCatalog::logicalPathIsUsable($tenantId, $path)) {
                $ok++;
            } else {
                $missing[] = $path;
            }
        }

        $this->info('Тенант '.$tenant->slug.' (id='.$tenantId.'), ролей в выборке: '.$roleFilter.'.');
        $this->line('Проверено уникальных путей: '.count($paths).' (ok: '.$ok.', нет на диске/реплике: '.count($missing).').');

        if ($missing !== []) {
            $this->newLine();
            $this->warn('Отсутствуют (primary/replica public storage):');
            foreach (array_slice($missing, 0, 40) as $p) {
                $url = ExpertBrandMediaUrl::resolve($p);
                $this->line('  - '.$p.($url !== '' ? '  →  '.$url : ''));
            }
            if (count($missing) > 40) {
                $this->line('  … ещё '.(count($missing) - 40).' путей.');
            }
            $this->newLine();
            $this->line('Что сделать: залить файлы в tenants/{id}/public/… (R2 или зеркало), затем на сервере:');
            $this->line('  php artisan tenant:black-duck:refresh-content '.$tenant->slug.' --force');
            $this->line('См. docs/tenant/black-duck-media-layout.md и docs/operations/tenant-media-local-mirror.md.');
        }

        if ($missing !== [] && (bool) $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
