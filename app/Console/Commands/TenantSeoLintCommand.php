<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantForSeoCommands;
use App\Services\Seo\TenantSeoLintService;
use Illuminate\Console\Command;

class TenantSeoLintCommand extends Command
{
    use ResolvesTenantForSeoCommands;

    protected $signature = 'tenant-seo:lint {tenant : Tenant ID or slug} {--http : Use real HTTP to tenant URLs instead of internal kernel} {--json : Output JSON}';

    protected $description = 'Run SEO quality checks for a tenant (internal mode by default)';

    public function handle(TenantSeoLintService $lint): int
    {
        $tenant = $this->resolveTenantForSeo((string) $this->argument('tenant'));
        $result = $lint->lint($tenant, (bool) $this->option('http'));

        if ($this->option('json')) {
            $this->line(json_encode($result->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return count($result->errors) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->line('Score: '.$result->score.'/100');
        $this->line('Checked: '.count($result->checkedPages).' URL(s)');
        foreach ($result->errors as $e) {
            $this->error($e);
        }
        foreach ($result->warnings as $w) {
            $this->warn($w);
        }
        foreach ($result->notices as $n) {
            $this->line('Notice: '.$n);
        }

        return count($result->errors) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
