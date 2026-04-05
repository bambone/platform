<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantForSeoCommands;
use App\Services\Seo\InitializeTenantSeoDefaults;
use Illuminate\Console\Command;

class TenantSeoBootstrapCommand extends Command
{
    use ResolvesTenantForSeoCommands;

    protected $signature = 'tenant-seo:bootstrap {tenant : Tenant ID or slug} {--force : Overwrite autopilot-managed settings} {--dry-run : Show actions without writing}';

    protected $description = 'Apply tenant SEO autopilot defaults (llms, route_overrides, optional home SeoMeta). --force overwrites those plus home SeoMeta when a published home page exists.';

    public function handle(InitializeTenantSeoDefaults $init): int
    {
        $tenant = $this->resolveTenantForSeo((string) $this->argument('tenant'));
        $result = $init->execute($tenant, (bool) $this->option('force'), (bool) $this->option('dry-run'));

        foreach ($result->messages as $line) {
            $this->line($line);
        }
        if ($result->messages === []) {
            $this->line('No changes (settings may already be filled; use --force to overwrite).');
        }

        $this->info('Done for tenant '.$tenant->slug.' (id '.$tenant->id.').');

        return self::SUCCESS;
    }
}
