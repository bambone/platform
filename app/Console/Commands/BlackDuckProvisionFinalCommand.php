<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use Illuminate\Console\Command;

/**
 * Один прогон «как на проде после сидов»: настройки + полный refresh контента (с удалением встроенного expert_lead при --force-логике).
 * Картинки и бандл hero не копируются — см. {@see BlackDuckImportAssetsCommand}, {@see BlackDuckImportHomeHeroWebpBundleCommand}, {@see BlackDuckImportServiceImagesCommand}.
 */
final class BlackDuckProvisionFinalCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:provision-final
                            {tenant=blackduck : slug или id}';

    protected $description = 'Black Duck: refresh-settings + refresh-content --force (без импорта файлов)';

    public function handle(BlackDuckContentRefresher $refresher): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
            $t = $refresher->resolveBlackDuckTenant();
            if ($t === null) {
                $this->error('Тенант Black Duck не найден. Сначала: db:seed --class=Database\\Seeders\\Tenant\\BlackDuckBootstrap');

                return self::FAILURE;
            }
            $tenant = $t;
        }

        if ($tenant->theme_key !== 'black_duck') {
            $this->error('Указан тенант не black_duck.');

            return self::FAILURE;
        }

        $refresher->refreshSettings($tenant, false, false, false, false);
        $this->line('OK: refresh-settings');

        $refresher->refreshContent($tenant, [
            'force' => true,
            'if_placeholder' => false,
            'only_seo' => false,
            'force_section' => null,
            'dry_run' => false,
        ]);
        $this->line('OK: refresh-content --force');
        $this->newLine();
        $this->info('Импорт медиа (при необходимости): tenant:black-duck:import-assets, tenant:black-duck:import-home-hero-bundle, tenant:black-duck:import-service-images.');

        return self::SUCCESS;
    }
}
