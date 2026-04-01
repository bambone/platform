<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Удаляет типичный «мусор» в storage/app/private: старые livewire-tmp, опционально папки вида {@code /{id}/}
 * без привязки к Spatie media на диске local.
 */
class PruneTenantStorageDebrisCommand extends Command
{
    protected $signature = 'storage:prune-tenant-debris
                            {--livewire-max-age-hours=48 : Удалить файлы в livewire-tmp старше N часов}
                            {--delete-orphan-numeric-roots : Удалить storage/app/private/{число}/ если нет media с этим id на disk=local}
                            {--dry-run : Только отчёт}';

    protected $description = 'Чистка livewire-tmp и опционально сиротских numeric-каталогов на private-диске';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('local');
        $maxAge = max(1, (int) $this->option('livewire-max-age-hours'));
        $cutoff = CarbonImmutable::now()->subHours($maxAge);

        $this->info("livewire-tmp: удаление файлов старше {$maxAge}ч (dry-run=".($dry ? 'yes' : 'no').')…');
        $tmpDir = 'livewire-tmp';
        if (! $disk->exists($tmpDir)) {
            $this->line('  каталог отсутствует');
        } else {
            $removed = 0;
            foreach ($disk->files($tmpDir) as $path) {
                if (! $this->isFileStale($disk, $path, $cutoff)) {
                    continue;
                }
                $this->line("  delete {$path}");
                $removed++;
                if (! $dry) {
                    $disk->delete($path);
                }
            }
            $this->info("  файлов к удалению: {$removed}");
        }

        if ($this->option('delete-orphan-numeric-roots')) {
            $this->info('numeric root dirs: проверка сирот…');
            $orphans = 0;
            foreach ($disk->directories() as $dir) {
                if (! preg_match('/^\d+$/', basename($dir))) {
                    continue;
                }
                $id = (int) basename($dir);
                $media = Media::query()->find($id);
                $isLocalMedia = $media !== null && $media->disk === 'local';
                if ($isLocalMedia) {
                    continue;
                }
                $this->warn("  orphan: {$dir}");
                $orphans++;
                if (! $dry) {
                    $disk->deleteDirectory($dir);
                }
            }
            $this->info("  сиротских каталогов: {$orphans}");
        } else {
            $this->line('(use --delete-orphan-numeric-roots для удаления папок вида private/9/)');
        }

        return self::SUCCESS;
    }

    private function isFileStale(Filesystem $disk, string $path, CarbonImmutable $cutoff): bool
    {
        try {
            $ts = $disk->lastModified($path);
        } catch (\Throwable) {
            return false;
        }

        return $ts < $cutoff->getTimestamp();
    }
}
