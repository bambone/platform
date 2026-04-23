<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use App\Tenant\BlackDuck\BlackDuckDuckMediaImporter;
use Illuminate\Console\Command;

/**
 * Копирует jpg/png/webp из каталога (редактор: {@code C:\Users\...}) в {@code site/brand/hub/} и
 * прописывает {@code image_url} в сетке услуг, кейсах и блоке «до/после».
 */
final class BlackDuckImportDuckMediaCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:black-duck:import-duck-media
                            {tenant=blackduck : slug или id}
                            {--source= : Каталог с фото (рекурсивно, без logo/hero) — по умолчанию C:\Users\g-man\Desktop\duck}
                            {--dry-run : Только отчёт, без записи}';

    protected $description = 'Black Duck: импорт фото в hub и привязка к карточкам/кейсам/до—после';

    public function handle(
        BlackDuckDuckMediaImporter $importer,
        BlackDuckContentRefresher $refresher,
    ): int {
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

        $raw = (string) $this->option('source');
        if (trim($raw) === '') {
            if (DIRECTORY_SEPARATOR === '\\') {
                $raw = 'C:\Users\g-man\Desktop\duck';
            } else {
                $this->error('Укажите --source=путь к каталогу с фото (например, копия папки duck с ПК).');

                return self::FAILURE;
            }
        }
        $raw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if (! is_dir($raw) && ! is_file($raw)) {
            $this->error('Нет пути: '.$raw);

            return self::FAILURE;
        }

        $files = $importer->collectImageFiles(is_file($raw) ? dirname($raw) : $raw);
        $this->info('Найдено изображений: '.count($files).'.');
        if ($files === []) {
            $this->warn('Пусто: проверьте каталог и форматы (jpg, png, webp).');

            return self::FAILURE;
        }
        if (count($files) < 3) {
            $this->warn('Мало файлов: часть слотов зациклится. Рекомендуется 15+ снимков.');
        }

        if ((bool) $this->option('dry-run')) {
            $this->info('[dry-run] Ассеты в хранилище и JSON не меняем.');

            return self::SUCCESS;
        }

        $keys = $importer->importFromSourceDirectory(
            $tenant,
            is_file($raw) ? dirname($raw) : $raw,
            false,
        );
        if ($keys === []) {
            $this->error('Импорт не создал файлов (ошибка записи или пусто после фильтра).');

            return self::FAILURE;
        }

        $this->info('OK: скопировано '.count($keys).' файлов в site/brand/hub/; секции page_sections обновлены.');

        return self::SUCCESS;
    }
}
