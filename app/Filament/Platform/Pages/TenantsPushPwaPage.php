<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use App\Models\Tenant;
use App\Services\TenantPush\TenantPushPlatformOwnedSettingsService;
use App\TenantPush\PlatformTenantPushTablePresenter;
use App\TenantPush\TenantPushCrmRequestRecipientResolver;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushOverride;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TenantsPushPwaPage extends Page
{
    use GrantsPlatformPageAccess;

    protected static ?string $navigationLabel = 'Push & PWA';

    protected static ?string $title = 'Клиенты: Push и PWA';

    protected static ?string $slug = 'tenants-push-pwa';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Клиенты';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.platform.tenants-push-pwa';

    /**
     * @return list<\App\TenantPush\PlatformTenantPushTableRow>
     */
    public function getTableRowsProperty(): array
    {
        $tenants = Tenant::query()
            ->with(['pushSettings', 'plan'])
            ->orderBy('name')
            ->limit(500)
            ->get();

        return PlatformTenantPushTablePresenter::rowsForTenants(
            $tenants,
            app(TenantPushFeatureGate::class),
            app(TenantPushCrmRequestRecipientResolver::class),
        );
    }

    public function platformQuickAction(int $tenantId, string $action): void
    {
        abort_unless(static::canAccess(), 403);

        $tenant = Tenant::query()->findOrFail($tenantId);
        $gate = app(TenantPushFeatureGate::class);
        $cur = $gate->findSettings($tenant);

        $override = TenantPushOverride::tryFrom((string) ($cur?->push_override ?? '')) ?? TenantPushOverride::InheritPlan;
        $commercial = (bool) ($cur?->commercial_service_active ?? false);
        $selfServe = (bool) ($cur?->self_serve_allowed ?? true);

        if ($action === 'inherit') {
            $override = TenantPushOverride::InheritPlan;
        } elseif ($action === 'force_enable') {
            $override = TenantPushOverride::ForceEnable;
        } elseif ($action === 'force_disable') {
            $override = TenantPushOverride::ForceDisable;
        } elseif ($action === 'commercial_on') {
            $commercial = true;
        } elseif ($action === 'commercial_off') {
            $commercial = false;
        } else {
            return;
        }

        app(TenantPushPlatformOwnedSettingsService::class)->applyScalars(
            $tenant,
            $override,
            $commercial,
            $selfServe,
            Auth::user(),
        );

        $tenant->refresh();
        $saved = $gate->findSettings($tenant);
        $overrideAfter = TenantPushOverride::tryFrom((string) ($saved?->push_override ?? '')) ?? TenantPushOverride::InheritPlan;

        $title = 'Для клиента «'.((string) $tenant->name).'»';
        $actionLine = match ($action) {
            'inherit' => 'Установлено: переопределение — как в тарифе.',
            'force_enable' => 'Установлено: принудительно включено (оверрайд).',
            'force_disable' => 'Установлено: принудительно выключено (оверрайд).',
            'commercial_on' => 'Коммерческая активация: включена.',
            'commercial_off' => 'Коммерческая активация: выключена.',
            default => 'Настройки обновлены.',
        };
        $stateLine = sprintf(
            'Текущее состояние: оверрайд — %s; коммерция — %s; самообслуживание в кабинете — %s.',
            $overrideAfter->platformLabel(),
            ((bool) ($saved?->commercial_service_active ?? false)) ? 'да' : 'нет',
            ((bool) ($saved?->self_serve_allowed ?? true)) ? 'да' : 'нет',
        );

        Notification::make()
            ->title($title)
            ->body($actionLine.' '.$stateLine)
            ->success()
            ->send();
    }
}
