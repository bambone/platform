<?php

namespace App\Product\CRM;

use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;

/**
 * Только презентация workspace CRM: цвета, иконки таймлайна.
 * Бизнес-правила и meta остаются в моделях/сервисе.
 */
final class CrmWorkspacePresentation
{
    /**
     * Классы для бейджа статуса (dark-friendly, приглушённые).
     */
    public static function statusBadgeClasses(?string $status): string
    {
        $token = CrmRequest::statusColor($status);

        return self::filamentTokenToBadgeClasses($token);
    }

    /**
     * Классы для бейджа приоритета.
     */
    public static function priorityBadgeClasses(?string $priority): string
    {
        $token = CrmRequest::priorityColor($priority ?? CrmRequest::PRIORITY_NORMAL);

        return self::filamentTokenToBadgeClasses($token);
    }

    public static function filamentTokenToBadgeClasses(string $token): string
    {
        return match ($token) {
            'info' => 'bg-sky-500/10 text-sky-200 ring-1 ring-inset ring-sky-500/25',
            'warning' => 'bg-amber-500/10 text-amber-200 ring-1 ring-inset ring-amber-500/25',
            'success' => 'bg-emerald-500/10 text-emerald-200 ring-1 ring-inset ring-emerald-500/25',
            'danger' => 'bg-rose-500/10 text-rose-200 ring-1 ring-inset ring-rose-500/25',
            default => 'bg-zinc-500/10 text-zinc-300 ring-1 ring-inset ring-zinc-500/20',
        };
    }

    /**
     * @return array{icon: string, iconWrap: string, rail: string}
     */
    public static function activityTimelineVisuals(CrmRequestActivity $activity): array
    {
        return match ($activity->type) {
            CrmRequestActivity::TYPE_INBOUND_RECEIVED => [
                'icon' => 'heroicon-o-inbox',
                'iconWrap' => 'bg-sky-500/15 text-sky-300',
                'rail' => 'border-s-sky-500/45',
            ],
            CrmRequestActivity::TYPE_STATUS_CHANGED => [
                'icon' => 'heroicon-o-arrow-path',
                'iconWrap' => 'bg-amber-500/15 text-amber-300',
                'rail' => 'border-s-amber-500/45',
            ],
            CrmRequestActivity::TYPE_NOTE_ADDED => [
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'iconWrap' => 'bg-violet-500/15 text-violet-300',
                'rail' => 'border-s-violet-500/45',
            ],
            CrmRequestActivity::TYPE_FOLLOW_UP_SET => [
                'icon' => 'heroicon-o-clock',
                'iconWrap' => 'bg-cyan-500/15 text-cyan-300',
                'rail' => 'border-s-cyan-500/45',
            ],
            CrmRequestActivity::TYPE_PRIORITY_CHANGED => [
                'icon' => 'heroicon-o-signal',
                'iconWrap' => 'bg-orange-500/15 text-orange-300',
                'rail' => 'border-s-orange-500/45',
            ],
            CrmRequestActivity::TYPE_SUMMARY_UPDATED => [
                'icon' => 'heroicon-o-document-text',
                'iconWrap' => 'bg-zinc-500/20 text-zinc-300',
                'rail' => 'border-s-zinc-500/40',
            ],
            CrmRequestActivity::TYPE_MAIL_QUEUED => [
                'icon' => 'heroicon-o-envelope',
                'iconWrap' => 'bg-blue-500/15 text-blue-300',
                'rail' => 'border-s-blue-500/45',
            ],
            CrmRequestActivity::TYPE_ASSIGNED => [
                'icon' => 'heroicon-o-user',
                'iconWrap' => 'bg-teal-500/15 text-teal-300',
                'rail' => 'border-s-teal-500/45',
            ],
            default => [
                'icon' => 'heroicon-o-bolt',
                'iconWrap' => 'bg-zinc-500/15 text-zinc-400',
                'rail' => 'border-s-zinc-500/35',
            ],
        };
    }
}
