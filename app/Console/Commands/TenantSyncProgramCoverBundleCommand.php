<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantArgument;
use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Console\Command;

/**
 * Повторная заливка бандла WebP в публичный диск (например после переключения на R2 или смены файлов в seeders).
 */
final class TenantSyncProgramCoverBundleCommand extends Command
{
    use ResolvesTenantArgument;

    protected $signature = 'tenant:sync-program-cover-bundle {tenant=aflyatunov : slug или id тенанта}';

    protected $description = 'Upload bundled program WebP covers to expert_auto/programs/{slug}/ on tenant public storage; sets cover_image_ref, cover_mobile_ref, cover_image_alt';

    public function handle(): int
    {
        $tenant = $this->resolveTenant((string) $this->argument('tenant'));
        AflyatunovExpertBootstrap::syncProgramCoverAssetsToTenantPublicDisk((int) $tenant->id);
        $disk = config('tenant_storage.public_disk', 'public');
        $this->info("Program covers synced for tenant «{$tenant->slug}» (public disk: {$disk}).");

        return self::SUCCESS;
    }
}
