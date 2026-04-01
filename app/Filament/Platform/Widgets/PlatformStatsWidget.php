<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use App\Models\TenantMailLog;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends BaseStatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static ?string $panel = 'platform';

    protected function getStats(): array
    {
        $activeClients = Tenant::query()->where('status', 'active')->count();
        $trialClients = Tenant::query()->where('status', 'trial')->count();
        $newTenantsLast7Days = Tenant::query()
            ->where('created_at', '>=', Carbon::today()->subDays(6)->startOfDay())
            ->count();

        $sentMails = TenantMailLog::query()->where('status', TenantMailLog::STATUS_SENT)->count();
        $errorMails = TenantMailLog::query()->where('status', TenantMailLog::STATUS_FAILED)->count();
        $throttledRows = TenantMailLog::query()->where('throttled_count', '>', 0)->count();

        $clientsChart = $this->dailyCountsForLast7Days(
            fn (Carbon $day): int => Tenant::query()->whereDate('created_at', $day)->count()
        );

        $sentChart = $this->dailyCountsForLast7Days(
            fn (Carbon $day): int => TenantMailLog::query()
                ->where('status', TenantMailLog::STATUS_SENT)
                ->where(function ($q) use ($day): void {
                    $q->whereDate('sent_at', $day)
                        ->orWhere(function ($q2) use ($day): void {
                            $q2->whereNull('sent_at')->whereDate('created_at', $day);
                        });
                })
                ->count()
        );

        $failedChart = $this->dailyCountsForLast7Days(
            fn (Carbon $day): int => TenantMailLog::query()
                ->where('status', TenantMailLog::STATUS_FAILED)
                ->where(function ($q) use ($day): void {
                    $q->whereDate('failed_at', $day)
                        ->orWhere(function ($q2) use ($day): void {
                            $q2->whereNull('failed_at')->whereDate('created_at', $day);
                        });
                })
                ->count()
        );

        $throttledChart = $this->dailyCountsForLast7Days(
            fn (Carbon $day): int => TenantMailLog::query()
                ->where('throttled_count', '>', 0)
                ->whereDate('created_at', $day)
                ->count()
        );

        $trialPart = $trialClients > 0 ? "На пробном: {$trialClients}" : 'Без пробных';
        $newPart = $newTenantsLast7Days > 0 ? "Новых за 7 дн.: {$newTenantsLast7Days}" : 'Новых за 7 дн.: 0';

        return [
            Stat::make('Активных клиентов', (string) $activeClients)
                ->description("{$trialPart} · {$newPart}")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->chart($clientsChart)
                ->color('success'),

            Stat::make('Отправлено писем', (string) $sentMails)
                ->description('tenant_mail_logs, статус «отправлено»')
                ->chart($sentChart)
                ->color('primary'),

            Stat::make('Ошибки доставки', (string) $errorMails)
                ->description('tenant_mail_logs, статус «ошибка»')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->chart($failedChart)
                ->color('danger'),

            Stat::make('Throttled (лимит)', (string) $throttledRows)
                ->description('Записей с throttled_count > 0')
                ->chart($throttledChart)
                ->color('warning'),
        ];
    }

    /**
     * @param  callable(Carbon $day): int  $counter
     * @return list<int>
     */
    private function dailyCountsForLast7Days(callable $counter): array
    {
        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $out[] = max(0, (int) $counter($day));
        }

        return $out;
    }
}
