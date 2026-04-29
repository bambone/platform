<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Tenant\MagasExpertBootstrap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Переставляет тенанта {@see MagasExpertBootstrap} на канонический {@see MagasExpertBootstrap::CANONICAL_TENANT_ID}, если строка уже создана другим AUTO_INCREMENT (например id=7 при свободных 5,6).
 */
final class TenantMagasReassignCanonicalIdCommand extends Command
{
    protected $signature = 'tenant:magas:reassign-canonical-id 
                            {--from= : Исходный id (по умолчанию из slug sergey-magas)}
                            {--dry-run : Только счётчики, без изменений}';

    protected $description = 'Перенести все tenant_id связанные с Sergei Magas на id из CANONICAL_TENANT_ID (5 при свободном слоте).';

    public function handle(): int
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            $this->error('Команда рассчитана на MySQL/MariaDB (информация из INFORMATION_SCHEMA + FOREIGN_KEY_CHECKS).');

            return self::FAILURE;
        }

        $toId = MagasExpertBootstrap::CANONICAL_TENANT_ID;
        $slug = MagasExpertBootstrap::SLUG;

        $fromId = trim((string) $this->option('from'));
        if ($fromId === '') {
            $fromId = (string) DB::table('tenants')->where('slug', $slug)->value('id');
        }
        $fromId = (int) $fromId;

        if ($fromId <= 0) {
            $this->error('Тенант со slug '.$slug.' не найден — сначала tenant:magas:bootstrap');

            return self::FAILURE;
        }

        if ($fromId === $toId) {
            $this->info('Тенант '.$slug.' уже с id='.$toId.'.');

            return self::SUCCESS;
        }

        $busy = (bool) DB::table('tenants')->where('id', $toId)->exists();
        if ($busy) {
            $this->error('Строка tenants.id='.$toId.' уже занята. Освободите id или переименуйте вручную — операция отменена.');

            return self::FAILURE;
        }

        $slugCheck = DB::table('tenants')->where('id', $fromId)->value('slug');
        if ((string) $slugCheck !== $slug) {
            $this->error('Под id='.$fromId.' другой slug ('.$slugCheck.') — операция отменена.');

            return self::FAILURE;
        }

        $tables = $this->mysqlTablesWithTenantIdColumn();
        $orphanByTable = $this->countOrphanTenantRows($tables, $toId);
        $orphanTotal = array_sum($orphanByTable);

        /** @var array<string, int> $counts table => rows at fromId */
        $counts = [];
        foreach ($tables as $t) {
            if ($t === 'tenants') {
                continue;
            }
            try {
                $n = (int) DB::table($t)->where('tenant_id', $fromId)->count();
            } catch (\Throwable) {
                $n = -1;
            }
            $counts[$t] = $n;
        }

        $this->line('Перенести tenant_id '.$fromId.' → '.$toId.' для slug '.$slug);
        if ($orphanTotal > 0) {
            $this->warn('Сироты с tenant_id='.$toId.' без строки в tenants (конфликт уникальных ключей при merge) — будут удалены:');
            foreach ($orphanByTable as $table => $n) {
                if ($n > 0) {
                    $this->line(sprintf('  [orphan] %s: %d строк', $table, $n));
                }
            }
        }
        foreach ($counts as $t => $n) {
            if ($n !== 0) {
                $this->line(sprintf('  %s: %d строк', $t, $n));
            }
        }

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry-run: изменений не внесено.');

            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            if ($orphanTotal > 0) {
                $this->deleteOrphanTenantRows($tables, $toId);
            }
            foreach ($tables as $table) {
                if ($table === 'tenants') {
                    continue;
                }
                DB::table($table)->where('tenant_id', $fromId)->update(['tenant_id' => $toId]);
            }

            DB::table('tenants')->where('id', $fromId)->update([
                'id' => $toId,
                'updated_at' => now(),
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $max = (int) (DB::table('tenants')->max('id') ?? 0);
        DB::statement('ALTER TABLE tenants AUTO_INCREMENT = '.($max + 1));

        $this->info('Готово: тенант '.$slug.' теперь id='.$toId.'; AUTO_INCREMENT таблицы tenants = '.($max + 1).'.');
        $this->warn('Если в public storage уже есть префикс tenants/'.$fromId.'/, перенесите объекты на tenants/'.$toId.'/ (S3/R2/local) — иначе ссылки на файлы останутся старыми.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function countOrphanTenantRows(array $tables, int $toId): array
    {
        if (DB::table('tenants')->where('id', $toId)->exists()) {
            return [];
        }
        $out = [];
        foreach ($tables as $table) {
            if ($table === 'tenants') {
                continue;
            }
            try {
                $out[$table] = (int) DB::table($table)->where('tenant_id', $toId)->count();
            } catch (\Throwable) {
                $out[$table] = 0;
            }
        }

        return $out;
    }

    /**
     * Хвосты с tenant_id без строки в tenants (мешают unique при merge на свободный id).
     *
     * @param  list<string>  $tables
     */
    private function deleteOrphanTenantRows(array $tables, int $toId): void
    {
        if (DB::table('tenants')->where('id', $toId)->exists()) {
            return;
        }
        foreach ($tables as $table) {
            if ($table === 'tenants') {
                continue;
            }
            DB::table($table)->where('tenant_id', $toId)->delete();
        }
    }

    /**
     * @return list<string>
     */
    private function mysqlTablesWithTenantIdColumn(): array
    {
        $db = (string) DB::connection()->getDatabaseName();
        /** @var list<object{TABLE_NAME: string}> $rows */
        $rows = DB::select(
            'SELECT DISTINCT TABLE_NAME AS TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?
             ORDER BY TABLE_NAME ASC',
            [$db, 'tenant_id'],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = $r->TABLE_NAME;
        }

        return $out;
    }
}
