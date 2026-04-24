<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TenantServiceProgramResource\Concerns;

use App\Http\Controllers\HomeController;
use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Tenant\BlackDuck\BlackDuckContentConstants;
use App\Tenant\BlackDuck\BlackDuckContentRefresher;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * После изменения каталога услуг Black Duck пересобирает публичные секции (тот же контракт, что и {@see BlackDuckContentRefresher::refreshContent}).
 */
trait RefreshesBlackDuckTenantPublicContent
{
    protected function afterBlackDuckServiceProgramMutation(TenantServiceProgram $record): void
    {
        $tenant = Tenant::query()->find($record->tenant_id);
        if ($tenant === null || (string) $tenant->theme_key !== BlackDuckContentConstants::THEME_KEY) {
            return;
        }
        try {
            app(BlackDuckContentRefresher::class)->refreshContent($tenant, [
                'force' => false,
                'if_placeholder' => true,
                'only_seo' => false,
                'force_section' => null,
                'dry_run' => false,
            ]);
            HomeController::forgetCachedPayloadForTenant((int) $tenant->id);
        } catch (Throwable $e) {
            Log::error('Black Duck: refresh after service program save failed', [
                'tenant_id' => $tenant->id,
                'exception' => $e->getMessage(),
            ]);
            Notification::make()
                ->title('Не удалось обновить публичные секции')
                ->body('Выполните вручную: php artisan tenant:black-duck:refresh-content '.$tenant->slug)
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
