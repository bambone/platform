<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\BlackDuck\BlackDuckTenantRuntimeHealth;
use Illuminate\Console\Command;

/**
 * Секции страниц, FAQ, контент, SEO (без смены tenant_settings, кроме косвенно через applySeo).
 * --if-placeholder: не трогать секции, если не похожи на bootstrap-заглушки.
 * --force: перезаписать управляемые секции и FAQ без маркеров-заглушек; уже существующие отзывы в кабинете не затираются без явного `--overwrite-reviews`.
 * --overwrite-reviews: явно перезаписать управляемые отзывы (site/import и кураторские с карт) после правок в админке / осознанный re-seed.
 * --overwrite-editorial-case-list: на /raboty подменить case_list из медиакаталога, даже если content_source=manual_db или кейсы из Filament (см. BlackDuckRabotyCaseListContentSource).
 */
final class BlackDuckRefreshContentCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:refresh-content
                            {tenant=blackduck : slug или id}
                            {--dry-run : План без записи}
                            {--force : Перезаписать управляемые секции/FAQ даже без маркеров-заглушек; отзывы не затираются, если они уже есть — см. --overwrite-reviews}
                            {--if-placeholder=1 : 1/0 — только обновлять плейсхолдеры (по умолчанию 1)}
                            {--only-seo : Только SeoMeta/JSON-LD (без секций и FAQ)}
                            {--force-section= : Только section_key (например expert_hero)}
                            {--overwrite-editorial-case-list : Перезаписать case_list на /raboty из каталога (игнор manual_db / кейсы Filament)}
                            {--overwrite-reviews : Перезаписать отзывы (site/import + кураторские с карт); без этого --force не затирает уже существующие отзывы}';

    protected $description = 'Black Duck: обновить page_sections, FAQ, SEO и при необходимости отзывы (см. --overwrite-reviews)';

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
            'overwrite_editorial_case_list' => (bool) $this->option('overwrite-editorial-case-list'),
            'overwrite_reviews' => (bool) $this->option('overwrite-reviews'),
        ]);

        if ((bool) $this->option('dry-run')) {
            $this->info('[dry-run] Контент/SEO (tenant_id='.$tenant->id.').');
        } else {
            $this->info('Готово: tenant:black-duck:refresh-content.');
        }

        return self::SUCCESS;
    }
}
