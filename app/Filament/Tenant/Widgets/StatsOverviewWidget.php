<?php

namespace App\Filament\Tenant\Widgets;

use App\Filament\Tenant\Resources\LeadResource;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Операции';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = Carbon::today();

        $leadsToday = Lead::whereDate('created_at', $today)->count();
        $newLeads = Lead::where('status', 'new')->count();
        $inProgressLeads = Lead::whereIn('status', ['new', 'in_progress'])->count();

        $leadsIndex = LeadResource::getUrl('index');

        return [
            Stat::make('Заявок сегодня', $leadsToday)
                ->icon('heroicon-o-calendar-days')
                ->description($today->format('d.m.Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color($leadsToday > 0 ? 'success' : 'gray')
                ->url($leadsIndex)
                ->extraAttributes([
                    'class' => 'fi-tenant-dash-stat'.($leadsToday > 0 ? ' fi-tenant-dash-stat--ok' : ''),
                ]),
            Stat::make('Новых заявок', $newLeads)
                ->icon('heroicon-o-inbox-arrow-down')
                ->description('Требуют обработки')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($newLeads > 0 ? 'warning' : 'gray')
                ->url($leadsIndex)
                ->extraAttributes([
                    'class' => 'fi-tenant-dash-stat'.($newLeads > 0 ? ' fi-tenant-dash-stat--warn' : ''),
                ]),
            Stat::make('В работе', $inProgressLeads)
                ->icon('heroicon-o-arrow-path')
                ->description('Новые + в работе')
                ->descriptionIcon('heroicon-m-clock')
                ->color($inProgressLeads > 0 ? 'info' : 'gray')
                ->url($leadsIndex)
                ->extraAttributes([
                    'class' => 'fi-tenant-dash-stat'.($inProgressLeads > 0 ? ' fi-tenant-dash-stat--info' : ''),
                ]),
        ];
    }
}
