<?php

namespace App\Filament\Shared\CRM;

use App\Models\CrmRequest;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class CrmRequestStatsHelper
{
    /**
     * @return array<int, Stat>
     */
    public static function stats(Builder $base): array
    {
        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();

        $newToday = (clone $base)->where('created_at', '>=', $today)->count();

        $awaitingReply = (clone $base)
            ->where('status', CrmRequest::STATUS_AWAITING_REPLY)
            ->count();

        $overdueFollowUp = (clone $base)->needsFollowUp()->count();

        $convertedWeek = (clone $base)
            ->where('status', CrmRequest::STATUS_CONVERTED)
            ->where('updated_at', '>=', $weekStart)
            ->count();

        $rejectedWeek = (clone $base)
            ->where('status', CrmRequest::STATUS_REJECTED)
            ->where('updated_at', '>=', $weekStart)
            ->count();

        return [
            Stat::make('Новые сегодня', $newToday)
                ->description($today->format('d.m.Y'))
                ->color($newToday > 0 ? 'success' : 'gray'),
            Stat::make('Ждём ответ', $awaitingReply)
                ->description('Статус «Ждём ответ»')
                ->color($awaitingReply > 0 ? 'warning' : 'gray'),
            Stat::make('Просрочен follow-up', $overdueFollowUp)
                ->description('Не терминальные заявки')
                ->color($overdueFollowUp > 0 ? 'danger' : 'gray'),
            Stat::make('Конверсии за неделю', $convertedWeek)
                ->description('С '.$weekStart->format('d.m'))
                ->color('success'),
            Stat::make('Отклонено за неделю', $rejectedWeek)
                ->description('С '.$weekStart->format('d.m'))
                ->color('gray'),
        ];
    }
}
