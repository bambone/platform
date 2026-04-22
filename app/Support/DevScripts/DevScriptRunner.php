<?php

namespace App\Support\DevScripts;

use InvalidArgumentException;
use Symfony\Component\Process\Process;

final class DevScriptRunner
{
    private const int MAX_OUTPUT_CHARS = 600_000;

    /**
     * @param  array{media_target?: string, dump_path?: string}  $context
     * @return array{success: bool, exit_code: int|null, output: string, script_id: string, label: string}
     */
    public function run(string $scriptId, array $context = []): array
    {
        $def = DevScriptRegistry::get($scriptId);
        if (isset($def['available']) && ! ($def['available'])()) {
            throw new InvalidArgumentException('Скрипт недоступен на этой ОС.');
        }

        $timeout = (float) ($def['timeout'] ?? 600);
        set_time_limit((int) max($timeout + 120, 3600));

        $runner = $def['runner'];
        $exit = null;
        $parts = [];

        if ($runner === 'process') {
            [$exit, $parts] = $this->runProcess($def, $context, $timeout);
        } elseif ($runner === 'artisan') {
            [$exit, $parts] = $this->runArtisanOnce($def, $context, $timeout);
        } elseif ($runner === 'artisan_chain') {
            [$exit, $parts] = $this->runArtisanChain($def, $context, $timeout);
        } else {
            throw new InvalidArgumentException('Unknown runner');
        }

        $combined = $this->mergeOutput($parts);
        $success = $exit === 0;

        return [
            'success' => $success,
            'exit_code' => $exit,
            'output' => $combined,
            'script_id' => $def['id'],
            'label' => $def['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array{media_target?: string, dump_path?: string}  $context
     * @return array{0: int|null, 1: list<string>}
     */
    private function runProcess(array $def, array $context, float $timeout): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = $def['windows_command'] ?? null;
        } else {
            $cmd = $def['unix_command'] ?? null;
        }
        if (! is_array($cmd) || $cmd === []) {
            throw new InvalidArgumentException('Не задана команда для этой платформы.');
        }

        $resolved = $this->resolveExecutableCommand($cmd);
        $resolved = $this->appendPowerShellArgsForScript((string) $def['id'], $resolved, $context);

        $extraEnv = $def['run_env'] ?? [];
        $process = new Process($resolved, base_path(), null, null, $timeout);
        $exit = $process->run(null, $extraEnv);

        return [$exit, [$process->getOutput(), $process->getErrorOutput()]];
    }

    /**
     * @param  list<string>  $cmd
     * @return list<string>
     */
    private function resolveExecutableCommand(array $cmd): array
    {
        $out = [];
        $n = count($cmd);
        for ($i = 0; $i < $n; $i++) {
            $part = (string) $cmd[$i];
            if (strtoupper($part) === '-FILE' && $i + 1 < $n) {
                $out[] = $part;
                $i++;
                $out[] = $this->toAbsoluteProjectPath((string) $cmd[$i]);

                continue;
            }
            if ($i === 1 && str_contains(strtolower((string) $cmd[0]), 'bash')) {
                $out[] = $this->toAbsoluteProjectPath($part);

                continue;
            }
            $out[] = $part;
        }

        return $out;
    }

    /**
     * @param  list<string>  $cmd
     * @return list<string>
     */
    private function appendPowerShellArgsForScript(string $scriptId, array $cmd, array $context): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $cmd;
        }
        $target = trim((string) ($context['media_target'] ?? ''));
        if ($target === '') {
            $target = (string) env('MEDIA_LOCAL_ROOT', '');
        }
        if ($scriptId === 'sync-r2-powershell') {
            if ($target === '' || ! self::isAbsolutePath($target)) {
                throw new InvalidArgumentException('Для PowerShell-обёртки укажите абсолютный каталог зеркала или MEDIA_LOCAL_ROOT.');
            }

            return array_merge($cmd, ['-Target', $target]);
        }
        if ($scriptId === 'bootstrap-stage-from-prod') {
            if ($target === '') {
                $target = (string) env('MEDIA_LOCAL_ROOT', '');
            }
            if ($target === '' || ! self::isAbsolutePath($target)) {
                throw new InvalidArgumentException('Для bootstrap укажите абсолютный каталог зеркала (поле ниже) или MEDIA_LOCAL_ROOT в .env.');
            }

            return array_merge($cmd, ['-MediaTarget', $target]);
        }

        return $cmd;
    }

    private function toAbsoluteProjectPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:/#', $normalized) === 1) {
            return $path;
        }

        return base_path(trim($normalized, '/'));
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array{media_target?: string, dump_path?: string}  $context
     * @return array{0: int|null, 1: list<string>}
     */
    private function runArtisanOnce(array $def, array $context, float $timeout): array
    {
        $base = $def['artisan'] ?? null;
        if (! is_array($base)) {
            throw new InvalidArgumentException('artisan command missing');
        }

        $args = $base;
        if (($def['id'] ?? '') === 'sync-r2-public-media') {
            $target = trim((string) ($context['media_target'] ?? ''));
            if ($target === '') {
                $target = (string) env('MEDIA_LOCAL_ROOT', '');
            }
            if ($target === '') {
                throw new InvalidArgumentException('Укажите каталог зеркала или MEDIA_LOCAL_ROOT в .env.');
            }
            if (! self::isAbsolutePath($target)) {
                throw new InvalidArgumentException('Путь к зеркалу должен быть абсолютным.');
            }
            $args = array_merge($args, ['--target', $target]);
        }

        $buffer = '';
        $code = $this->invokeArtisanProcess($args, $timeout, $buffer);

        return [$code, [$buffer, '']];
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array{media_target?: string, dump_path?: string}  $context
     * @return array{0: int|null, 1: list<string>}
     */
    private function runArtisanChain(array $def, array $context, float $timeout): array
    {
        if (($def['id'] ?? '') !== 'full-mysql-import') {
            throw new InvalidArgumentException('artisan_chain не настроен');
        }
        $path = trim((string) ($context['dump_path'] ?? ''));
        if ($path === '' || ! is_readable($path)) {
            throw new InvalidArgumentException('Укажите существующий абсолютный путь к .sql дампу.');
        }

        $chunks = [];
        $exit = 0;

        $buffer1 = '';
        $exit = $this->invokeArtisanProcess(['rentbase:import-mysql-dump', $path, '--no-interaction'], $timeout, $buffer1);
        $chunks[] = "=== rentbase:import-mysql-dump ===\n".$buffer1;
        if ($exit !== 0) {
            return [$exit, $chunks];
        }

        $buffer2 = '';
        $exit = $this->invokeArtisanProcess(['migrate', '--force', '--no-interaction'], $timeout, $buffer2);
        $chunks[] = "=== migrate --force ===\n".$buffer2;

        return [$exit, $chunks];
    }

    /**
     * @param  list<string>  $args
     */
    private function invokeArtisanProcess(array $args, float $timeout, string &$buffer): int
    {
        $cmd = array_merge([PHP_BINARY, base_path('artisan')], $args);
        $process = new Process($cmd, base_path(), null, null, $timeout);
        $code = $process->run();
        $buffer = trim($process->getOutput()."\n".$process->getErrorOutput());

        return $code;
    }

    /**
     * @param  list<string>  $parts
     */
    private function mergeOutput(array $parts): string
    {
        $text = trim(implode("\n", array_map('strval', $parts)));
        $text = $this->bytesToUtf8Text($text);
        if (strlen($text) > self::MAX_OUTPUT_CHARS) {
            $text = substr($text, 0, self::MAX_OUTPUT_CHARS)."\n\n… [вывод обрезан, см. логи на диске при необходимости]";
            $text = $this->bytesToUtf8Text($text);
        }

        return $text;
    }

    /**
     * Subprocess output (especially PowerShell/cmd on Windows) may not be valid UTF-8.
     * Livewire serializes public properties with JSON_THROW_ON_ERROR — scrub before storing output.
     */
    private function bytesToUtf8Text(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        if (mb_check_encoding($bytes, 'UTF-8')) {
            return mb_scrub($bytes, 'UTF-8');
        }

        foreach (['Windows-1251', 'CP866', 'CP1252', 'ISO-8859-1'] as $encoding) {
            $converted = mb_convert_encoding($bytes, 'UTF-8', $encoding);
            if (mb_check_encoding($converted, 'UTF-8')) {
                return mb_scrub($converted, 'UTF-8');
            }
        }

        $stripped = @iconv('UTF-8', 'UTF-8//IGNORE', $bytes);

        return mb_scrub($stripped !== false ? $stripped : $bytes, 'UTF-8');
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
