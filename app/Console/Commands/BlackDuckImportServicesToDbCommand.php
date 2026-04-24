<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Models\TenantServiceProgram;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckServiceImages;
use App\Tenant\BlackDuck\BlackDuckServiceRegistry;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DB-first услуги Black Duck: синхронизирует PHP-реестр услуг в `tenant_service_programs`,
 * чтобы список и карточки редактировались через админку.
 *
 * Бинарники (обложки) остаются в public storage (`site/brand/services/{slug}.webp`).
 */
final class BlackDuckImportServicesToDbCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:import-services-to-db
                            {tenant=blackduck : slug или id}
                            {--wipe : Удалить существующие tenant_service_programs для тенанта перед импортом}
                            {--only-missing : Не обновлять существующие записи (только создать отсутствующие)}
                            {--refresh : После импорта выполнить tenant:black-duck:refresh-content --force}';

    protected $description = 'Black Duck: импортировать услуги из реестра в БД (tenant_service_programs)';

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
        $rows = BlackDuckServiceRegistry::all();
        if ($rows === []) {
            $this->warn('Реестр услуг пуст.');

            return self::SUCCESS;
        }

        $onlyMissing = (bool) $this->option('only-missing');
        $homeSubtitles = BlackDuckContentConstants::homeServiceCardPreviewSubtitlesBySlug();

        DB::transaction(function () use ($tenantId, $rows, $onlyMissing, $homeSubtitles): void {
            if ((bool) $this->option('wipe')) {
                TenantServiceProgram::query()->where('tenant_id', $tenantId)->delete();
            }

            foreach ($rows as $r) {
                $slug = trim((string) ($r['slug'] ?? ''));
                if ($slug === '' || str_starts_with($slug, '#')) {
                    continue;
                }
                if (mb_strlen($slug, 'UTF-8') > TenantServiceProgram::SLUG_MAX_LENGTH) {
                    $this->warn('Пропуск услуги: slug длиннее '.TenantServiceProgram::SLUG_MAX_LENGTH.' (публичная форма): '.substr($slug, 0, 24).'…');

                    continue;
                }

                $existing = TenantServiceProgram::query()
                    ->where('tenant_id', $tenantId)
                    ->where('slug', $slug)
                    ->first();

                if ($existing !== null && $onlyMissing) {
                    continue;
                }

                $cover = BlackDuckServiceImages::firstServiceHubCardPublicPath($tenantId, $slug);
                $priceAnchor = BlackDuckServiceRegistry::publicPriceAnchorForSlug($slug);
                $data = [
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'title' => (string) ($r['title'] ?? $slug),
                    'teaser' => (string) ($r['blurb'] ?? ''),
                    'description' => (string) ($r['body_intro'] ?? ''),
                    'duration_label' => '',
                    'price_amount' => null,
                    'price_prefix' => 'от',
                    'format_label' => '',
                    'program_type' => ServiceProgramType::Program->value,
                    'is_featured' => (bool) ($r['is_featured'] ?? false),
                    'is_visible' => true,
                    'sort_order' => (int) ($r['service_sort'] ?? 0),
                    'cover_image_ref' => $cover,
                    'cover_mobile_ref' => null,
                    'cover_image_alt' => (string) ($r['title'] ?? $slug),
                    'catalog_meta_json' => [
                        'group_key' => (string) ($r['group_key'] ?? ''),
                        'group_title' => (string) ($r['group_title'] ?? ''),
                        'group_blurb' => (string) ($r['group_blurb'] ?? ''),
                        'group_sort' => (int) ($r['group_sort'] ?? 0),
                        'booking_mode' => (string) ($r['booking_mode'] ?? ''),
                        'has_landing' => (bool) ($r['has_landing'] ?? false),
                        'show_on_home' => (bool) ($r['show_on_home'] ?? false),
                        'show_in_catalog' => (bool) ($r['show_in_catalog'] ?? true),
                        'public_price_anchor' => $priceAnchor !== null && $priceAnchor !== '' ? (string) $priceAnchor : '',
                        'home_card_subtitle' => (string) ($homeSubtitles[$slug] ?? ''),
                        'included_items' => is_array($r['included_items'] ?? null) ? array_values($r['included_items']) : [],
                    ],
                ];

                if ($existing === null) {
                    TenantServiceProgram::query()->create($data);
                } else {
                    $existing->fill($data);
                    $existing->save();
                }
            }
        });

        $this->info('Готово: услуги синхронизированы в tenant_service_programs.');

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

