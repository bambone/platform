<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static ?string $panel = 'platform';

    protected function getStats(): array
    {
        $tenantCount = Tenant::count();
        $withoutDomain = Tenant::query()
            ->whereDoesntHave('domains')
            ->count();
        $trialCount = Tenant::where('status', 'trial')->count();
        $activeCount = Tenant::where('status', 'active')->count();

        return [
            Stat::make('Клиентов', (string) $tenantCount)
                ->description('Все клиенты платформы')
                ->color('primary'),
            Stat::make('Без домена', (string) $withoutDomain)
                ->description($withoutDomain > 0 ? 'Нужно добавить адрес сайта' : 'У всех есть хотя бы один домен')
                ->color($withoutDomain > 0 ? 'danger' : 'success'),
            Stat::make('Активных', (string) $activeCount)
                ->description('Статус «Активен» — полноценная работа')
                ->color('success'),
            Stat::make('Пробный период', (string) $trialCount)
                ->description('Статус «Пробный»')
                ->color('warning'),
        ];
    }
}
