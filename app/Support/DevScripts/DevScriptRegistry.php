<?php

namespace App\Support\DevScripts;

/**
 * Реестр dev-скриптов для UI (только local, см. PlatformDevScriptsPage).
 *
 * @phpstan-type ScriptDef array{
 *   id: string,
 *   label: string,
 *   description: string,
 *   runner: 'process'|'artisan'|'artisan_chain',
 *   timeout?: float,
 *   requires_confirm?: bool,
 *   confirm_message?: string,
 *   available?: callable(): bool,
 *   windows_command?: list<string>,
 *   unix_command?: list<string>,
 *   run_env?: array<string, string>,
 *   artisan?: list<string>,
 *   artisan_chain?: list<list<string>>,
 * }
 */
final class DevScriptRegistry
{
    /**
     * @return list<ScriptDef>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'restore-stage-from-backup',
                'label' => 'Восстановление БД (whitelist из бэкапа)',
                'description' => 'PowerShell/Bash: временная БД, переливка таблиц из restore-include.txt, проверка redirects, optimize:clear + migrate. Нужны zstd, mysql, rclone (или локальный .sql через -SqlPath в терминале). Env: CONFIRM_STAGE_RESTORE=yes, RENTBASE_RESTORE_RCLONE_REMOTE, опционально RCLONE_CONFIG.',
                'runner' => 'process',
                'timeout' => 7200,
                'requires_confirm' => true,
                'confirm_message' => 'Это перезапишет данные whitelist-таблиц в локальной БД. Продолжить?',
                'windows_command' => ['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/restore-stage-from-backup.ps1'],
                'unix_command' => ['bash', 'scripts/restore-stage-from-backup.sh'],
                'run_env' => ['CONFIRM_STAGE_RESTORE' => 'yes'],
            ],
            [
                'id' => 'bootstrap-stage-from-prod',
                'label' => 'Bootstrap: БД + R2 (Windows)',
                'description' => 'Последовательно restore-stage-from-backup.ps1 и sync-r2-public-media-to-local.ps1. На Linux используйте отдельные сценарии.',
                'runner' => 'process',
                'timeout' => 10800,
                'requires_confirm' => true,
                'confirm_message' => 'Долгая операция: переливка БД и загрузка медиа. Продолжить?',
                'available' => fn (): bool => PHP_OS_FAMILY === 'Windows',
                'windows_command' => ['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/bootstrap-stage-from-prod.ps1'],
                'run_env' => ['CONFIRM_STAGE_RESTORE' => 'yes'],
            ],
            [
                'id' => 'sync-r2-public-media',
                'label' => 'R2 → локальное зеркало (artisan)',
                'description' => 'tenant-media:backfill-from-r2. Укажите каталог ниже или задайте MEDIA_LOCAL_ROOT в .env.',
                'runner' => 'artisan',
                'timeout' => 7200,
                'requires_confirm' => false,
                'artisan' => ['tenant-media:backfill-from-r2', '--no-interaction'],
            ],
            [
                'id' => 'sync-r2-powershell',
                'label' => 'R2 → локальное зеркало (PowerShell-обёртка)',
                'description' => 'То же через sync-r2-public-media-to-local.ps1 (проверки APP_ENV/DB_HOST в скрипте).',
                'runner' => 'process',
                'timeout' => 7200,
                'requires_confirm' => false,
                'available' => fn (): bool => PHP_OS_FAMILY === 'Windows',
                'windows_command' => ['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/sync-r2-public-media-to-local.ps1'],
            ],
            [
                'id' => 'full-mysql-import',
                'label' => 'Полный импорт дампа (rentbase:import-mysql-dump + migrate)',
                'description' => 'Заменяет БД целиком по одному .sql (как sync-local-db-from-prod-dump.ps1). Укажите абсолютный путь к файлу.',
                'runner' => 'artisan_chain',
                'timeout' => 7200,
                'requires_confirm' => true,
                'confirm_message' => 'Полная замена БД из дампа. Продолжить?',
            ],
            [
                'id' => 'export-changed-files-since-push',
                'label' => 'Экспорт изменённых файлов (git)',
                'description' => 'export-changed-files-since-push.ps1 → scripts/export-changed-files-dumps/',
                'runner' => 'process',
                'timeout' => 600,
                'requires_confirm' => false,
                'available' => fn (): bool => PHP_OS_FAMILY === 'Windows',
                'windows_command' => ['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'scripts/export-changed-files-since-push.ps1'],
            ],
        ];
    }

    /**
     * @return ScriptDef
     */
    public static function get(string $id): array
    {
        foreach (self::all() as $script) {
            if ($script['id'] === $id) {
                return $script;
            }
        }

        abort(404, 'Unknown script');
    }

    /**
     * @return list<ScriptDef>
     */
    public static function availableScripts(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $s): bool => ! isset($s['available']) || ($s['available'])(),
        ));
    }
}
