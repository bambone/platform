<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Кладёт в репозиторий (resources) минимальные PNG для всех путей, на которые ссылается тема moto в Blade.
 * Полноценные макеты можно подменить файлами с теми же именами; затем {@see SyncThemePlatformAssetsCommand}.
 */
class ThemePublishBundledAssetsCommand extends Command
{
    /** 1×1 PNG (прозрачный пиксель) — заглушка до замены дизайнерскими файлами */
    private const PLACEHOLDER_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private const RELATIVE_PATHS_MOTO = [
        'marketing/hero-bg.png',
        'marketing/logo-round-dark.png',
        'marketing/experience-coastal.png',
        'marketing/experience-city.png',
        'marketing/experience-touring.png',
        'avatars/avatar-1.png',
        'avatars/avatar-2.png',
        'avatars/avatar-3.png',
        'icons/icon-192.png',
    ];

    protected $signature = 'theme:publish-bundled
                            {theme_key=moto : Ключ темы}
                            {--force : Перезаписать существующие файлы}';

    protected $description = 'Создаёт resources/themes/{key}/public/… с PNG-заглушками для шаблонов (если файла ещё нет)';

    public function handle(): int
    {
        $key = strtolower(trim((string) $this->argument('theme_key')));
        if ($key === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $key)) {
            $this->error('Некорректный theme_key.');

            return self::FAILURE;
        }

        if ($key !== 'moto') {
            $this->warn('Список путей задан только для темы moto; для других ключей команда не создаёт файлы.');

            return self::SUCCESS;
        }

        $bytes = base64_decode(self::PLACEHOLDER_PNG_BASE64, true);
        if ($bytes === false) {
            $this->error('Внутренняя ошибка декодирования заглушки.');

            return self::FAILURE;
        }

        $root = resource_path('themes/'.$key.'/public');
        $written = 0;
        $skipped = 0;

        foreach (self::RELATIVE_PATHS_MOTO as $rel) {
            $dest = $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (File::exists($dest) && ! $this->option('force')) {
                $skipped++;

                continue;
            }
            File::ensureDirectoryExists(dirname($dest));
            File::put($dest, $bytes);
            $this->line("  + {$rel}");
            $written++;
        }

        $this->info("Записано файлов: {$written}, пропущено (уже есть): {$skipped} → {$root}");
        $this->comment('Опционально: php artisan theme:sync-platform-assets — скопировать в public/themes для отдачи как статика.');

        return self::SUCCESS;
    }
}
