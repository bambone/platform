<?php

namespace App\Filesystem;

use Illuminate\Filesystem\Filesystem;

/**
 * На Windows `Filesystem::replace()` (tempnam + rename) часто падает с «Отказано в доступе»
 * при перекомпиляции view, если целевой .php удерживается другим процессом (IDE, антивирус, второй PHP).
 * Для каталога скомпилированных Blade-шаблонов используем запись на место с LOCK_EX.
 */
class WindowsSafeFilesystem extends Filesystem
{
    /**
     * @param  string  $path
     * @param  string  $content
     * @param  int|null  $mode
     */
    public function replace($path, $content, $mode = null): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            parent::replace($path, $content, $mode);

            return;
        }

        $normalized = str_replace('\\', '/', (string) $path);
        if (! str_contains($normalized, 'framework/views')) {
            parent::replace($path, $content, $mode);

            return;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            $this->makeDirectory($directory, 0755, true);
        }

        if (is_file($path)) {
            @chmod($path, 0666);
        }

        $written = @file_put_contents($path, $content, LOCK_EX);
        if ($written === false) {
            parent::replace($path, $content, $mode);

            return;
        }

        if ($mode !== null) {
            @chmod($path, $mode);
        } elseif (is_file($path)) {
            @chmod($path, 0777 - umask());
        }
    }
}
