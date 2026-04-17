<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use App\Models\Tenant;
use Filament\Pages\Page;
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
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    public function getTenantsProperty()
    {
        return Tenant::query()
            ->with(['pushSettings', 'plan'])
            ->orderBy('name')
            ->limit(500)
            ->get();
    }
}
