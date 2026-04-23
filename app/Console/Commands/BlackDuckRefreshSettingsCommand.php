<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use Illuminate\Console\Command;

/**
 * Канал: настройки контактов, бренда, брони/форм, SEO (LocalBusiness) для Black Duck.
 * Режим: одноразовый first fill или селективно {@see self::$signature}. Повторный прогон перезаписывает
 * эти поля (ручные правки в «Настройках» — после прогонить с --dry-run и не публиковать без нужды).
 */
final class BlackDuckRefreshSettingsCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:refresh-settings
                            {tenant=blackduck : slug или id тенанта (theme_key=black_duck)}
                            {--dry-run : Показать, что сделал бы, без записи}
                            {--only-seo : Только SeoMeta / JSON-LD}
                            {--only-contacts : Только contacts.*, каналы, форма брони}
                            {--only-branding : Только general.* (домен, имя, описание)}';

    protected $description = 'Black Duck: обновить публичные настройки (contacts, branding, брони, SEO)';

    public function handle(BlackDuckContentRefresher $refresher): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
            $t = $refresher->resolveBlackDuckTenant();
            if ($t === null) {
                $this->error('Тенант Black Duck не найден. Сначала: php artisan db:seed --class=Database\\Seeders\\Tenant\\BlackDuckBootstrap');

                return self::FAILURE;
            }
            $tenant = $t;
        }

        if ($tenant->theme_key !== 'black_duck') {
            $this->error('Указан тенант не black_duck.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->info('[dry-run] Заполнили бы настройки и SEO для tenant_id='.$tenant->id);

            return self::SUCCESS;
        }

        $refresher->refreshSettings(
            $tenant,
            false,
            (bool) $this->option('only-seo'),
            (bool) $this->option('only-contacts'),
            (bool) $this->option('only-branding'),
        );
        $this->info('Готово: tenant:black-duck:refresh-settings (tenant_id='.$tenant->id.').');

        return self::SUCCESS;
    }
}
