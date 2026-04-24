<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\BlackDuck\BlackDuckTenantRuntimeHealth;
use Illuminate\Console\Command;

/**
 * Секции страниц, FAQ, отзывы, контент (без смены tenant_settings, кроме косвенно через applySeo).
 * --if-placeholder: не трогать секции, если не похожи на bootstrap-заглушки. --force: перезаписать всё управляемое.
 */
final class BlackDuckRefreshContentCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:refresh-content
                            {tenant=blackduck : slug или id}
                            {--dry-run : План без записи}
                            {--force : Перезаписать секции/FAQ/отзывы, даже без маркеров-заглушек}
                            {--if-placeholder=1 : 1/0 — только обновлять плейсхолдеры (по умолчанию 1)}
                            {--only-seo : Только SeoMeta/JSON-LD (без секций и FAQ)}
                            {--force-section= : Только section_key (например expert_hero)}';

    protected $description = 'Black Duck: обновить page_sections, FAQ, отзывы, SEO';

    public function handle(BlackDuckContentRefresher $refresher): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
            $t = $refresher->resolveBlackDuckTenant();
            if ($t === null) {
                $this->error('Тенант Black Duck не найден. Сначала выполните BlackDuckBootstrap.');

                return self::FAILURE;
            }
            $tenant = $t;
        }

        if ($tenant->theme_key !== 'black_duck') {
            $this->error('Указан тенант не black_duck.');

            return self::FAILURE;
        }

        $tid = (int) $tenant->id;
        if (BlackDuckTenantRuntimeHealth::isMediaRuntimeEmptyInDatabase($tid)) {
            $this->warn(
                'ВНИМАНИЕ: tenant_media_assets пуста — публичный каталог читает БД; /raboty, proof и т.п. останутся пустыми, пока не выполните: php artisan tenant:black-duck:import-media-catalog-to-db',
            );
        }
        if (BlackDuckTenantRuntimeHealth::isServiceCatalogDegradedForInquiryForm($tid)) {
            $this->warn(
                'ВНИМАНИЕ: нет видимых программ/услуг в tenant_service_programs — форма контактов не покажет селектор направлений, inquiry не привязывается к услуге.',
            );
        }

        $rawP = $this->option('if-placeholder');
        $ifPlaceholder = in_array($rawP, [false, 0, '0', 'false', 'off', 'no'], true)
            ? false
            : (in_array($rawP, [true, 1, '1', 'true', 'on', 'yes'], true) || $rawP === null);

        $refresher->refreshContent($tenant, [
            'force' => (bool) $this->option('force'),
            'if_placeholder' => $ifPlaceholder,
            'only_seo' => (bool) $this->option('only-seo'),
            'force_section' => $this->option('force-section') ? (string) $this->option('force-section') : null,
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        if ((bool) $this->option('dry-run')) {
            $this->info('[dry-run] Контент/SEO (tenant_id='.$tenant->id.').');
        } else {
            $this->info('Готово: tenant:black-duck:refresh-content.');
        }

        return self::SUCCESS;
    }
}
