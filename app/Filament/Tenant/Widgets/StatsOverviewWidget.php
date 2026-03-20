<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Lead;
use App\Models\Motorcycle;
use App\Models\Page;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();

        $leadsToday = Lead::whereDate('created_at', $today)->count();
        $newLeads = Lead::where('status', 'new')->count();
        $inProgressLeads = Lead::whereIn('status', ['new', 'in_progress'])->count();
        $motorcyclesCount = Motorcycle::where('show_in_catalog', true)->where('status', 'available')->count();

        $pagesWithoutSeo = Page::whereDoesntHave('seoMeta')->where('status', 'published')->count();
        $motorcyclesWithoutSeo = Motorcycle::where('show_in_catalog', true)
            ->whereDoesntHave('seoMeta')
            ->count();
        $missingSeo = $pagesWithoutSeo + $motorcyclesWithoutSeo;

        return [
            Stat::make('Заявок сегодня', $leadsToday)
                ->description($today->format('d.m.Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color($leadsToday > 0 ? 'success' : 'gray')
                ->url('/admin/leads'),
            Stat::make('Новых заявок', $newLeads)
                ->description('Требуют обработки')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($newLeads > 0 ? 'warning' : 'gray')
                ->url('/admin/leads'),
            Stat::make('В работе', $inProgressLeads)
                ->description('Новые + в работе')
                ->descriptionIcon('heroicon-m-clock')
                ->url('/admin/leads'),
            Stat::make('Карточек в каталоге', $motorcyclesCount)
                ->description('Доступны для бронирования (статус «Доступен» и показ в каталоге)')
                ->descriptionIcon('heroicon-m-truck')
                ->url('/admin/motorcycles'),
            Stat::make('Без SEO', $missingSeo)
                ->description($missingSeo > 0 ? 'Страницы/модели без мета-тегов' : 'Всё в порядке')
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color($missingSeo > 0 ? 'warning' : 'success')
                ->url('/admin/pages'),
        ];
    }
}
