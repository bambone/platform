<?php

namespace App\Filesystem;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Throwable;

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

        $writeInPlace = function () use ($path, $content): bool {
            $written = @file_put_contents($path, $content, LOCK_EX);

            return $written !== false;
        };

        if (! $writeInPlace() && is_file($path)) {
            @unlink($path);
            $writeInPlace();
        }

        if (! is_file($path) || @filesize($path) !== strlen($content)) {
            $handle = @fopen($path, 'c+b');
            if ($handle !== false && flock($handle, LOCK_EX)) {
                ftruncate($handle, 0);
                rewind($handle);
                $n = fwrite($handle, $content);
                fflush($handle);
                flock($handle, LOCK_UN);
                fclose($handle);
                if ($n !== strlen($content)) {
                    parent::replace($path, $content, $mode);

                    return;
                }
            } else {
                parent::replace($path, $content, $mode);

                return;
            }
        }

        if ($mode !== null) {
            @chmod($path, $mode);
        } elseif (is_file($path)) {
            @chmod($path, 0777 - umask());
        }
    }

    /**
     * {@inheritdoc}
     *
     * На Windows чтение скомпилированного Blade из `storage/framework/views` иногда даёт
     * errno=13 (антивирус, блокировка файла). Тогда `hash_file` падает с предупреждением
     * → ErrorException в Laravel. Возвращаем false: BladeCompiler перезапишет файл.
     */
    public function hash($path, $algorithm = 'md5')
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized = str_replace('\\', '/', (string) $path);
            if (str_contains($normalized, 'framework/views')) {
                try {
                    $result = @hash_file($algorithm, $path);

                    return $result === false ? false : $result;
                } catch (\Throwable) {
                    return false;
                }
            }
        }

        return parent::hash($path, $algorithm);
    }

    /**
     * {@inheritdoc}
     *
     * Иногда `require` не может дочитать скомпилированный Blade (errno=13). Повторяем попытки
     * и при необходимости выполняем тот же PHP из копии в %TEMP% (меньше конфликтов с АВ/индексацией).
     */
    public function getRequire($path, array $data = [])
    {
        if (DIRECTORY_SEPARATOR !== '\\' || ! $this->isFrameworkCompiledViewsPath($path)) {
            return parent::getRequire($path, $data);
        }

        return $this->getRequireWithWindowsViewCacheWorkaround($path, $data, once: false);
    }

    /**
     * {@inheritdoc}
     */
    public function requireOnce($path, array $data = [])
    {
        if (DIRECTORY_SEPARATOR !== '\\' || ! $this->isFrameworkCompiledViewsPath($path)) {
            return parent::requireOnce($path, $data);
        }

        return $this->getRequireWithWindowsViewCacheWorkaround($path, $data, once: true);
    }

    private function isFrameworkCompiledViewsPath(string $path): bool
    {
        return str_contains(str_replace('\\', '/', $path), 'framework/views');
    }

    private function isWindowsTransientReadFailure(Throwable $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, 'Permission denied')
            || str_contains($m, 'errno=13')
            || str_contains($m, 'failed with errno=13');
    }

    /**
     * @return mixed
     */
    private function getRequireWithWindowsViewCacheWorkaround(string $path, array $data, bool $once)
    {
        if (! $this->isFile($path)) {
            throw new FileNotFoundException("File does not exist at path {$path}.");
        }

        $last = null;
        for ($i = 0; $i < 8; $i++) {
            try {
                clearstatcache(true, $path);

                return $once ? parent::requireOnce($path, $data) : parent::getRequire($path, $data);
            } catch (Throwable $e) {
                $last = $e;
                if (! $this->isWindowsTransientReadFailure($e)) {
                    throw $e;
                }
                usleep(10000 + ($i * 15000));
            }
        }

        $tmp = $this->tempCopyOfCompiledView($path);
        if ($tmp !== null) {
            try {
                return $once ? parent::requireOnce($tmp, $data) : parent::getRequire($tmp, $data);
            } finally {
                @unlink($tmp);
            }
        }

        throw $last;
    }

    private function tempCopyOfCompiledView(string $path): ?string
    {
        $contents = false;
        for ($i = 0; $i < 6; $i++) {
            clearstatcache(true, $path);
            $contents = @file_get_contents($path);
            if ($contents !== false) {
                break;
            }
            usleep(15000 + ($i * 20000));
        }
        if ($contents === false) {
            return null;
        }

        $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rb_lv_'.bin2hex(random_bytes(8)).'.php';
        if (@file_put_contents($target, $contents, LOCK_EX) === false) {
            return null;
        }

        return $target;
    }
}
