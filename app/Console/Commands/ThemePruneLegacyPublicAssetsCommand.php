<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Удаляет дубликаты платформенных ассетов темы из {@code public/} после переноса в S3/R2 и/или {@code resources/themes/.../public}.
 *
 * Убирает: {@code public/{legacy_asset_url_prefix}} (обычно images/motolevins) и {@code public/themes/{key}}.
 * Не трогает: {@code public/build}, Filament ({@code public/js|css|fonts/filament}), {@code index.php}, manifest, sw.
 */
class ThemePruneLegacyPublicAssetsCommand extends Command
{
    protected $signature = 'theme:prune-legacy-public
                            {--dry-run : Только показать, что будет удалено}
                            {--force : Выполнить без интерактивного подтверждения}
                            {--force. : То же, что --force (опечатка с точкой в конце)}';

    protected $description = 'Удалить дубликаты темы из public/images/motolevins и public/themes/* (основной контент — R2 + /theme/build/…)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $force = (bool) ($this->option('force') || $this->option('force.'));

        $targets = [];

        $legacy = trim((string) config('themes.legacy_asset_url_prefix', ''), '/');
        if ($legacy !== '') {
            $p = public_path($legacy);
            if (File::isDirectory($p)) {
                $targets[] = $p;
            }
        }

        $themesRoot = public_path(trim((string) config('themes.public_asset_root', 'themes'), '/'));
        if (File::isDirectory($themesRoot)) {
            foreach (File::directories($themesRoot) as $dir) {
                $targets[] = $dir;
            }
        }

        if ($targets === []) {
            $this->info('Нечего удалять: каталоги legacy/themes в public не найдены.');

            return self::SUCCESS;
        }

        $this->warn('Будут удалены только дубликаты темы в public/. Убедитесь, что:');
        $this->line('  • hero/медиа на витрине тянутся с R2 (tenant storage) или есть файлы в resources/themes/*/public/;');
        $this->line('  • иконки PWA: php artisan theme:publish-bundled и маршрут /theme/build/… (manifest.json).');
        $this->newLine();

        foreach ($targets as $path) {
            $this->line(($dry ? '[dry-run] ' : '').'remove: '.$path);
        }

        if ($dry) {
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Удалить перечисленные каталоги?', false)) {
            $this->warn('Отменено.');

            return self::FAILURE;
        }

        foreach ($targets as $path) {
            File::deleteDirectory($path);
            $this->info('Удалено: '.$path);
        }

        if (File::isDirectory($themesRoot) && File::isEmptyDirectory($themesRoot)) {
            @rmdir($themesRoot);
            $this->line('Пустой каталог удалён: '.$themesRoot);
        }

        $this->newLine();
        $this->comment('Локально: повторите при необходимости. На сервере: php artisan theme:prune-legacy-public --force (без точки после --force)');

        return self::SUCCESS;
    }
}
