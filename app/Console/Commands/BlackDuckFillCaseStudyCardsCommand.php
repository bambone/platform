<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckCaseStudyCardsFiller;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Заполняет секцию кейсов на /raboty (case_list / case_study_cards) из существующих public assets.
 */
final class BlackDuckFillCaseStudyCardsCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:fill-case-study-cards
                            {tenant=blackduck : slug или id тенанта}
                            {--source-dir= : Папка с локальными файлами для проверки имён (по умолчанию Desktop или SOURCE_IMAGES_DIR)}
                            {--dry-run : Только отчёт, без записи (по умолчанию если не указан --apply)}
                            {--apply : Записать в БД}
                            {--sync-missing : Скопировать отсутствующие файлы из source-dir в site/uploads/page-builder/case-study/ (не перезаписывает существующие)}
                            {--force : Перезаписать даже при ≥10 кейсах с валидными картинками}';

    protected $description = 'Black Duck: заполнить data_json.items секции кейсов на странице работ';

    public function handle(BlackDuckCaseStudyCardsFiller $filler): int
    {
        $key = (string) $this->argument('tenant');
        try {
            $tenant = $this->resolveTenant($key);
        } catch (Throwable) {
            $this->error('Тенант не найден: '.$key);

            return self::FAILURE;
        }

        if ($tenant->theme_key !== BlackDuckContentConstants::THEME_KEY) {
            $this->error('Тема тенанта должна быть '.BlackDuckContentConstants::THEME_KEY.' (сейчас: '.(string) $tenant->theme_key.').');

            return self::FAILURE;
        }

        $sourceDir = (string) ($this->option('source-dir') ?: getenv('SOURCE_IMAGES_DIR') ?: '');
        if ($sourceDir === '') {
            if (PHP_OS_FAMILY === 'Windows') {
                $sourceDir = 'C:\\Users\\g-man\\Desktop\\duck\\картинки';
            } else {
                $this->error('Укажите --source-dir или переменную SOURCE_IMAGES_DIR.');

                return self::FAILURE;
            }
        }

        $dryRun = ! (bool) $this->option('apply') || (bool) $this->option('dry-run');

        $this->line('SOURCE_IMAGES_DIR: '.$sourceDir);
        $this->line($dryRun ? 'Режим: dry-run (без записи в БД)' : 'Режим: APPLY (БД)');
        if ((bool) $this->option('sync-missing')) {
            $this->warn('Будет выполнено копирование отсутствующих файлов в tenant public storage (если указано --sync-missing).');
        }

        $result = $filler->run(
            $tenant,
            $sourceDir,
            $dryRun,
            (bool) $this->option('force'),
            (bool) $this->option('sync-missing'),
        );

        if (($result['skipped'] ?? false) === true) {
            $this->warn($result['reason'] ?? 'Пропуск.');
            foreach ($result['errors'] ?? [] as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $this->info('tenant_id='.($result['tenant_id'] ?? '?').' page_id='.($result['page_id'] ?? '?').' section_id='.($result['section_id'] ?? 'new'));
        $this->newLine();

        $this->line('Подбор файлов (basename → object_key → public_url):');
        foreach ($result['mapping'] ?? [] as $row) {
            $this->line(sprintf(
                '  [%d] %s → %s → %s',
                (int) ($row['slot'] ?? 0),
                (string) ($row['basename'] ?? ''),
                (string) ($row['object_key'] ?? ''),
                (string) ($row['public_url'] ?? '(null)'),
            ));
        }

        if (($result['excluded_primaries'] ?? []) !== []) {
            $this->newLine();
            $this->line('Исключения / замены primary:');
            foreach ($result['excluded_primaries'] as $ex) {
                $this->line('  слот '.($ex['slot'] ?? '?').' '.$ex['basename'].': '.$ex['reason']);
            }
        }

        $this->newLine();
        $this->line('data_json.items (итог):');
        $this->line(json_encode($result['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (($result['backup_json'] ?? null) !== null && $result['backup_json'] !== '') {
            $this->newLine();
            $this->line('Бэкап предыдущего data_json (фрагмент до '.strlen((string) $result['backup_json']).' симв.) залогирован через Log::info black_duck_case_study_cards_backup');
        }

        foreach ($result['errors'] ?? [] as $e) {
            if ($e !== '') {
                $this->error($e);
            }
        }

        if (($result['errors'] ?? []) !== []) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry-run завершён. Для записи: добавьте --apply');
        } else {
            $host = DB::table('tenant_domains')
                ->where('tenant_id', (int) $tenant->id)
                ->value('host');
            if (is_string($host) && $host !== '') {
                $this->newLine();
                $this->info('Проверка: GET http://'.$host.'/raboty');
            }
        }

        return self::SUCCESS;
    }
}
