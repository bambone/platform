<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Собирает {@code public/themes/{key}/} из:
 * 1) legacy {@code public/{legacy_asset_url_prefix}/} (если есть);
 * 2) {@code resources/themes/{key}/public/} — добивает отсутствующие файлы (заглушки из theme:publish-bundled).
 */
class SyncThemePlatformAssetsCommand extends Command
{
    protected $signature = 'theme:sync-platform-assets
                            {theme_key=moto : Ключ темы (каталог public/themes/{key})}
                            {--force : Перезаписать все существующие файлы в public/themes/{key}}';

    protected $description = 'Копирует ассеты темы в public/themes/{key} из legacy public и/или resources/themes';

    public function handle(): int
    {
        $key = strtolower(trim((string) $this->argument('theme_key')));
        if ($key === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $key)) {
            $this->error('Некорректный theme_key.');

            return self::FAILURE;
        }

        $to = public_path(trim((string) config('themes.public_asset_root', 'themes'), '/').'/'.$key);
        File::ensureDirectoryExists($to);

        $force = (bool) $this->option('force');
        $copied = 0;

        $legacy = trim((string) config('themes.legacy_asset_url_prefix', 'images/motolevins'), '/');
        $fromLegacy = public_path($legacy);
        if (is_dir($fromLegacy)) {
            $this->info('Копирование из public/'.$legacy);
            $copied += $this->copyTree($fromLegacy, $to, $force);
        } else {
            $this->warn('Legacy не найден: '.$fromLegacy);
        }

        $resourceSrc = resource_path('themes/'.$key.'/public');
        if (is_dir($resourceSrc)) {
            $this->info('Добивка из resources/themes/'.$key.'/public');
            $copied += $this->copyTree($resourceSrc, $to, $force);
        } else {
            $this->warn('Нет каталога: '.$resourceSrc.' — выполните php artisan theme:publish-bundled');
        }

        if ($copied === 0) {
            $this->error('Нечего копировать. Запустите: php artisan theme:publish-bundled, затем снова эту команду.');

            return self::FAILURE;
        }

        $this->info("Скопировано файлов: {$copied} → {$to}");

        return self::SUCCESS;
    }

    private function copyTree(string $from, string $to, bool $force): int
    {
        $n = 0;
        $fromNorm = str_replace('\\', '/', rtrim($from, '/\\'));
        foreach (File::allFiles($from) as $file) {
            $pathname = str_replace('\\', '/', $file->getPathname());
            $rel = ltrim(str_replace($fromNorm, '', $pathname), '/');
            $dest = $to.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            File::ensureDirectoryExists(dirname($dest));
            if (File::exists($dest) && ! $force) {
                continue;
            }
            File::copy($file->getPathname(), $dest);
            $this->line("  {$rel}");
            $n++;
        }

        return $n;
    }
}
