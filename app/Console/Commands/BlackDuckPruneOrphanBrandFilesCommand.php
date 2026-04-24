<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Support\Storage\TenantPublicMediaWriter;
use App\Tenant\BlackDuck\BlackDuckBrandReferencedPathIndex;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckMediaCatalog;
use Illuminate\Console\Command;

/**
 * Находит объекты в public storage под {@code site/brand/}, на которые нет ссылок из каталога/БД/секций/SEO/типовых кандидатов.
 * По умолчанию только печать; удаление — только с {@code --force} и внешним {@code --no-interaction} или подтверждением.
 */
final class BlackDuckPruneOrphanBrandFilesCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:prune-orphan-brand-files
                            {tenant=blackduck : slug или id}
                            {--force : Удалить сиротские файлы (иначе только список)}
                            {--yes : Подтвердить удаление без запроса (с --force; для CI/скриптов)}
                            {--limit=0 : Максимум удалений за один запуск (0 = без лимита)}';

    protected $description = 'Black Duck: сиротские файлы под site/brand/ (каталог БД + секции + пути из media-catalog.json на диске)';

    public function handle(TenantPublicMediaWriter $writer): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (\Throwable) {
            $this->error('Тенант не найден: '.$key);

            return self::FAILURE;
        }

        if ((string) $tenant->theme_key !== BlackDuckContentConstants::THEME_KEY) {
            $this->error('Тема тенанта не black_duck.');

            return self::FAILURE;
        }

        $tenantId = (int) $tenant->id;
        $refs = BlackDuckBrandReferencedPathIndex::collect($tenantId);
        $refKeys = array_fill_keys(array_keys($refs), true);

        $objectKeys = BlackDuckBrandReferencedPathIndex::allPublicFileObjectKeys($tenantId);
        $orphans = [];
        foreach ($objectKeys as $fullKey) {
            if (! is_string($fullKey) || $fullKey === '') {
                continue;
            }
            if (! preg_match('#^tenants/\d+/public/(site/brand/.+)$#', $fullKey, $m)) {
                continue;
            }
            $logical = BlackDuckMediaCatalog::normalizeLogicalKey($m[1]);
            if (isset($refKeys[$logical])) {
                continue;
            }
            $orphans[] = $fullKey;
        }
        natsort($orphans);
        $orphans = array_values($orphans);

        $this->info('Тенант '.$tenant->slug.' (id='.$tenantId.'), зона site/brand/:');
        $this->line('  учтённых логических путей: '.count($refKeys));
        $this->line('  файлов на диске: '.count($objectKeys));
        $this->line('  кандидатов на удаление (сироты): '.count($orphans));
        if ($orphans === []) {
            $this->info('Сирот нет — чистить нечего.');

            return self::SUCCESS;
        }

        $this->newLine();
        foreach ($orphans as $i => $k) {
            $this->line('  - '.$k);
            if ($i >= 200) {
                $rest = count($orphans) - 200;
                if ($rest > 0) {
                    $this->line('  … и ещё '.$rest.' (полный список в machine-readable при необходимости).');
                }
                break;
            }
        }
        if (count($orphans) > 200) {
            $this->warn('Показаны первые 200 путей; всего: '.count($orphans).'.');
        }

        if (! (bool) $this->option('force')) {
            $this->newLine();
            $this->comment('Режим просмотра. Для удаления: --force (и --yes в неинтерактивном режиме), после бэкапа и проверки списка.');

            return self::SUCCESS;
        }

        $confirmed = (bool) $this->option('yes')
            || $this->confirm('Удалить '.count($orphans).' объект(ов) из публичного storage тенанта '.$tenant->slug.'?', false);
        if (! $confirmed) {
            $this->warn('Отменено. Добавьте --yes для подтверждения в скрипте / CI.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $deleted = 0;
        $failed = 0;
        foreach ($orphans as $fullKey) {
            if ($limit > 0 && $deleted >= $limit) {
                $this->warn('Достигнут --limit='.$limit.'; остановка.');
                break;
            }
            try {
                if ($writer->deletePublicObjectKey($tenantId, $fullKey)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->info("Готово: удалено={$deleted}, не удалось={$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
