<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Смена numeric id тенанта (в т.ч. Black Duck 6 → 4) + копия префикса {@code tenants/{id}/} на публичных дисках.
 * Запустите до/после: {@code tenant-storage:sync-replica} на проде при необходимости.
 */
final class TenantRekeyIdCommand extends Command
{
    protected $signature = 'tenant:rekey-id
                        {from : Текущий id (например 6)}
                        {to : Целевой id (клиент production Black Duck: 4)}
                        {--skip-storage : Только БД, без копии файлов S3/зеркала}
                        {--force : Позволить, если target id уже занят (только вручную)}';

    protected $description = 'Переносит tenant_id в БД и (по умолчанию) копирует объекты из tenants/{from}/ в tenants/{to}/ на зеркале и r2-public';

    public function handle(): int
    {
        $from = (int) $this->argument('from');
        $to = (int) $this->argument('to');
        if ($from < 1 || $to < 1 || $from === $to) {
            $this->error('from/to должны быть положительными и разными.');

            return self::FAILURE;
        }

        $tFrom = DB::table('tenants')->where('id', $from)->first();
        if ($tFrom === null) {
            $this->error("Тенант id={$from} не найден.");

            return self::FAILURE;
        }
        if ((string) $tFrom->theme_key !== BlackDuckContentConstants::THEME_KEY) {
            $this->error('Сейчас команда сработает безопасно только для theme_key=black_duck. Отмена.');

            return self::FAILURE;
        }

        $tTo = DB::table('tenants')->where('id', $to)->first();
        if ($tTo !== null && ! (bool) $this->option('force')) {
            $this->error("Целевой id={$to} уже занят. Очистите вручную или используйте --force (опасно).");

            return self::FAILURE;
        }
        if ($tTo !== null && (bool) $this->option('force')) {
            $this->warn('--force: удаляю существующую строку tenants.id='.$to);
            try {
                DB::table('tenants')->where('id', $to)->delete();
            } catch (Throwable $e) {
                $this->error('Не удалось освободить id: '.$e->getMessage());
                report($e);

                return self::FAILURE;
            }
        }

        if (! (bool) $this->option('skip-storage')) {
            $this->replicateStoragePrefix($from, $to);
        }

        try {
            $this->rekeyDatabase($from, $to);
        } catch (Throwable $e) {
            $this->error('Ошибка БД: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info("Готово: тенант теперь id={$to}. Проверьте R2/зеркало и `tenant-storage:sync-replica` при dual-write.");

        return self::SUCCESS;
    }

    private function rekeyDatabase(int $from, int $to): void
    {
        TenantStorageQuotaService::withoutQuotaEnforcement(function () use ($from, $to): void {
            $tables = $this->tableNamesWithTenantIdColumn();
            if ($tables === []) {
                throw new RuntimeException('Не найдено таблиц с колонкой tenant_id.');
            }
            Schema::disableForeignKeyConstraints();
            try {
                DB::beginTransaction();
                foreach ($tables as $table) {
                    if ($table === 'tenants') {
                        continue;
                    }
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                        continue;
                    }
                    $n = (int) DB::table($table)->where('tenant_id', $from)->count();
                    if ($n < 1) {
                        continue;
                    }
                    try {
                        DB::table($table)->where('tenant_id', $from)->update(['tenant_id' => $to]);
                    } catch (QueryException $e) {
                        Log::warning('tenant:rekey-id: skip table (constraint)', ['table' => $table, 'e' => $e->getMessage()]);
                        $this->warn("Пропуск {$table}: ".$e->getMessage());
                    }
                }
                $u = (int) DB::table('tenants')->where('id', $from)->update(['id' => $to, 'updated_at' => now()]);
                if ($u !== 1) {
                    throw new RuntimeException('Ожидалась одна строка tenants, обновлено: '.$u);
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });
    }

    /**
     * @return list<string>
     */
    private function tableNamesWithTenantIdColumn(): array
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $db = (string) DB::connection()->getDatabaseName();
            $rows = DB::select('SELECT TABLE_NAME as n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ? GROUP BY TABLE_NAME', [$db, 'tenant_id']);
            $out = array_map(fn ($r) => (string) $r->n, $rows);
            sort($out);

            return $out;
        }
        if ($driver === 'sqlite') {
            $out = [];
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite\_%' ESCAPE '\'");
            foreach ($tables as $t) {
                $name = (string) $t->name;
                if (Schema::hasColumn($name, 'tenant_id')) {
                    $out[] = $name;
                }
            }
            sort($out);

            return $out;
        }
        if ($driver === 'pgsql') {
            $rows = DB::select("SELECT c.relname as n FROM pg_class c
                INNER JOIN pg_attribute a ON a.attrelid = c.oid AND a.attname = 'tenant_id'
                WHERE c.relkind = 'r' AND a.attnum > 0");
            $out = array_map(fn ($r) => (string) $r->n, $rows);
            sort($out);

            return $out;
        }

        throw new InvalidArgumentException('Неподдерживаемый драйвер: '.$driver);
    }

    private function replicateStoragePrefix(int $from, int $to): void
    {
        $fromBase = 'tenants/'.$from;
        $toBase = 'tenants/'.$to;
        foreach (['tenant-public-mirror', 'r2-public'] as $disk) {
            if (! is_string(config("filesystems.disks.{$disk}.driver"))) {
                $this->warn("Диск {$disk} не настроен, пропуск.");
                continue;
            }
            $d = \Illuminate\Support\Facades\Storage::disk($disk);
            try {
                $files = $d->allFiles($fromBase);
            } catch (Throwable) {
                $this->line("  [{$disk}] нет префикса {$fromBase}/");
                continue;
            }
            if ($files === []) {
                $this->line("  [{$disk}] пусто: {$fromBase}/");
                continue;
            }
            $this->line("  [{$disk}] копия {$fromBase} → {$toBase} …");
            foreach ($files as $path) {
                if (! str_starts_with((string) $path, $fromBase.'/') && (string) $path !== $fromBase) {
                    continue;
                }
                $rest = (string) $path;
                if (str_starts_with($rest, $fromBase.'/')) {
                    $rest = substr($rest, strlen($fromBase) + 1);
                } else {
                    $rest = '';
                }
                $dest = $toBase.($rest === '' ? '' : '/'.$rest);
                try {
                    $d->put($dest, $d->get($path), [
                        'CacheControl' => (string) config('tenant_storage.public_object_cache_control', 'public, max-age=31536000, immutable'),
                        'visibility' => 'public',
                    ]);
                } catch (Throwable $e) {
                    $this->error(" Не скопировал {$path}: ".$e->getMessage());
                    report($e);
                }
            }
        }
    }
}
