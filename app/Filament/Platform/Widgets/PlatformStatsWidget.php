<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static ?string $panel = 'platform';

    protected function getStats(): array
    {
        return [
            Stat::make('Клиентов', (string) Tenant::count())
                ->description('Всего в системе')
                ->color('primary'),
            Stat::make('Доменов', (string) TenantDomain::count())
                ->description('Записей tenant_domains')
                ->color('gray'),
            Stat::make('Активных', (string) Tenant::where('status', 'active')->count())
                ->description('Статус active')
                ->color('success'),
            Stat::make('Trial', (string) Tenant::where('status', 'trial')->count())
                ->description('Пробный период')
                ->color('warning'),
        ];
    }
}
