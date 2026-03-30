<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use mysqli;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rentbase:import-mysql-dump')]
class RentbaseImportMysqlDumpCommand extends Command
{
    protected $signature = 'rentbase:import-mysql-dump
                            {path : Absolute or project-relative path to a Navicat-style .sql dump (single schema)}
                            {--force : Run even when APP_ENV is not local}
                            {--skip-rewire : Do not rewrite *.rentbase.su hosts to *.rentbase.local for local resolution}
                            {--dry-run : Show what would run without changing the database}';

    protected $description = 'Replace the default MySQL database with a production dump (local dev). Optionally rewrites platform zone hosts to match TENANCY_ROOT_DOMAIN=.rentbase.local.';

    public function handle(): int
    {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->error('Refusing to import: set APP_ENV=local or pass --force.');

            return self::FAILURE;
        }

        $rawPath = $this->argument('path');
        $path = $this->resolveDumpPath($rawPath);
        if ($path === null || ! is_readable($path)) {
            $this->error('Dump file not found or not readable: '.$rawPath);

            return self::FAILURE;
        }

        $cfg = config('database.connections.'.config('database.default'));
        if (($cfg['driver'] ?? '') !== 'mysql') {
            $this->error('Default DB connection must be mysql.');

            return self::FAILURE;
        }

        $sql = file_get_contents($path);
        if ($sql === false || $sql === '') {
            $this->error('Dump is empty.');

            return self::FAILURE;
        }

        $this->warn('This will DROP and recreate all tables contained in the dump on database ['.($cfg['database'] ?? '').'].');
        if (! $this->option('dry-run') && ! $this->confirm('Continue?', true)) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: would import '.strlen($sql).' bytes, rewire='.($this->option('skip-rewire') ? 'no' : 'yes').'.');

            return self::SUCCESS;
        }

        $mysqli = $this->openMysqli($cfg);
        if ($mysqli === null) {
            return self::FAILURE;
        }

        if (! $mysqli->multi_query($sql)) {
            $this->error('Import failed: '.$mysqli->error);
            $mysqli->close();

            return self::FAILURE;
        }

        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->next_result());

        if ($mysqli->errno !== 0) {
            $this->error('Import failed after batch: '.$mysqli->error);
            $mysqli->close();

            return self::FAILURE;
        }

        $mysqli->close();

        $this->info('SQL import completed.');

        if (! $this->option('skip-rewire')) {
            $this->rewireLocalHosts();
        }

        $this->comment('Run `php artisan migrate --force` if your codebase has migrations newer than the dump.');

        return self::SUCCESS;
    }

    private function resolveDumpPath(string $raw): ?string
    {
        if (is_file($raw)) {
            return realpath($raw) ?: $raw;
        }

        $base = base_path(trim($raw, '/\\'));

        return is_file($base) ? (realpath($base) ?: $base) : null;
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function openMysqli(array $cfg): ?mysqli
    {
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 3306);
        $database = (string) ($cfg['database'] ?? '');
        $username = (string) ($cfg['username'] ?? 'root');
        $password = (string) ($cfg['password'] ?? '');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $mysqli = new mysqli($host, $username, $password, $database, $port);
        } catch (\Throwable $e) {
            $this->error('MySQL connection failed: '.$e->getMessage());

            return null;
        }

        $mysqli->set_charset('utf8mb4');

        return $mysqli;
    }

    private function rewireLocalHosts(): void
    {
        $this->info('Rewriting *.rentbase.su → *.rentbase.local where safe (tenant_domains, tenant_settings)…');

        DB::statement("UPDATE `tenant_domains` SET `host` = REPLACE(`host`, '.rentbase.su', '.rentbase.local') WHERE `host` LIKE '%.rentbase.su'");
        DB::statement("UPDATE `tenant_domains` SET `host` = 'rentbase.local' WHERE `host` = 'rentbase.su'");
        DB::statement("UPDATE `tenant_domains` SET `host` = 'www.rentbase.local' WHERE `host` = 'www.rentbase.su'");

        DB::statement("UPDATE `tenant_settings` SET `value` = REPLACE(`value`, '.rentbase.su', '.rentbase.local') WHERE `value` LIKE '%.rentbase.su%'");

        $this->info('Rewire done.');
    }
}
