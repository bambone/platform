<?php

namespace App\Filament\Platform\Widgets;

use App\Models\PlatformSetting;
use App\Models\TenantStorageQuota;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class PlatformStorageUsageWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected static ?string $panel = 'platform';

    protected function getStats(): array
    {
        $used = (int) TenantStorageQuota::query()->sum('used_bytes');
        $allocated = (int) TenantStorageQuota::query()
            ->selectRaw('COALESCE(SUM(base_quota_bytes + extra_quota_bytes), 0) as t')
            ->value('t');

        $physical = PlatformSetting::get('platform_storage.account_capacity_bytes', null);
        $physicalInt = is_numeric($physical) ? (int) $physical : null;

        $unusedAllocated = max(0, $allocated - $used);

        $stats = [
            Stat::make('Занято (tenant data)', Number::fileSize($used, precision: 1))
                ->description('Сумма used_bytes по клиентам')
                ->color('primary'),

            Stat::make('Выделено по квотам', Number::fileSize($allocated, precision: 1))
                ->description('Сумма base+extra по всем клиентам')
                ->color('gray'),
        ];

        if ($physicalInt !== null && $physicalInt > 0) {
            $stats[] = Stat::make('Ёмкость аккаунта (настройка)', Number::fileSize($physicalInt, precision: 1))
                ->description('platform_storage.account_capacity_bytes')
                ->color('success');

            $overcommit = $physicalInt > 0 ? round($allocated / $physicalInt, 2) : null;
            $stats[] = Stat::make('Квоты к ёмкости', $overcommit !== null ? (string) $overcommit : '—')
                ->description('Сумма выделенных квот ÷ ёмкость (overcommit)')
                ->color($overcommit !== null && $overcommit > 1 ? 'warning' : 'gray');

            $physUsedRatio = round($used / $physicalInt, 2);
            $stats[] = Stat::make('Tenant data к ёмкости', (string) $physUsedRatio)
                ->description('Фактически занято клиентами ÷ ёмкость')
                ->color('info');
        }

        $stats[] = Stat::make('Неиспользовано из выделенного', Number::fileSize($unusedAllocated, precision: 1))
            ->description('Выделено минус занято')
            ->color('info');

        $near = TenantStorageQuota::query()->whereIn('status', ['warning_20', 'critical_10', 'exceeded'])->count();
        $stats[] = Stat::make('Клиентов у лимита', (string) $near)
            ->description('warning / critical / exceeded')
            ->color($near > 0 ? 'warning' : 'success');

        return $stats;
    }
}
