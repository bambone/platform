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

    /**
     * Бейджи в шапке slide-over: только классы из crm-slideover-workspace.css (без Tailwind из PHP),
     * чтобы стили не зависели от content-scan.
     */
    public static function identityBadgeClassForStatus(?string $status): string
    {
        return 'crm-ws-badge crm-ws-badge--'.CrmRequest::statusColor($status);
    }

    public static function identityBadgeClassForPriority(?string $priority): string
    {
        return 'crm-ws-badge crm-ws-badge--'.CrmRequest::priorityColor($priority ?? CrmRequest::PRIORITY_NORMAL);
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
     * Filament shared CRM activity list (light + dark): dot, heroicon, emphasis.
     *
     * @return array{dot_classes: string, icon: string, is_important: bool}
     */
    public static function activityTimelineListRow(CrmRequestActivity $activity): array
    {
        return match ($activity->type) {
            CrmRequestActivity::TYPE_INBOUND_RECEIVED => [
                'dot_classes' => 'text-primary-500 bg-primary-50 dark:bg-primary-500/10',
                'icon' => 'heroicon-o-inbox-arrow-down',
                'is_important' => false,
            ],
            CrmRequestActivity::TYPE_STATUS_CHANGED => [
                'dot_classes' => 'text-success-500 bg-success-50 dark:bg-success-500/10',
                'icon' => 'heroicon-o-arrow-path',
                'is_important' => true,
            ],
            CrmRequestActivity::TYPE_NOTE_ADDED => [
                'dot_classes' => 'text-amber-500 bg-amber-50 dark:bg-amber-500/10',
                'icon' => 'heroicon-o-document-text',
                'is_important' => false,
            ],
            CrmRequestActivity::TYPE_MAIL_QUEUED => [
                'dot_classes' => 'text-info-500 bg-info-50 dark:bg-info-500/10',
                'icon' => 'heroicon-o-envelope',
                'is_important' => false,
            ],
            CrmRequestActivity::TYPE_TELEGRAM_QUEUED => [
                'dot_classes' => 'text-sky-500 bg-sky-50 dark:bg-sky-500/10',
                'icon' => 'heroicon-o-paper-airplane',
                'is_important' => false,
            ],
            CrmRequestActivity::TYPE_PRIORITY_CHANGED => [
                'dot_classes' => 'text-orange-500 bg-orange-50 dark:bg-orange-500/10',
                'icon' => 'heroicon-o-exclamation-triangle',
                'is_important' => true,
            ],
            CrmRequestActivity::TYPE_FOLLOW_UP_SET => [
                'dot_classes' => 'text-violet-500 bg-violet-50 dark:bg-violet-500/10',
                'icon' => 'heroicon-o-bell-alert',
                'is_important' => false,
            ],
            CrmRequestActivity::TYPE_SUMMARY_UPDATED => [
                'dot_classes' => 'text-teal-500 bg-teal-50 dark:bg-teal-500/10',
                'icon' => 'heroicon-o-clipboard-document-check',
                'is_important' => false,
            ],
            CrmRequestActivity::TYPE_ASSIGNED => [
                'dot_classes' => 'text-sky-500 bg-sky-50 dark:bg-sky-500/10',
                'icon' => 'heroicon-o-user-circle',
                'is_important' => false,
            ],
            default => [
                'dot_classes' => 'text-gray-500 bg-gray-100 dark:bg-zinc-800',
                'icon' => 'heroicon-o-clock',
                'is_important' => false,
            ],
        };
    }

    /**
     * Which meta template the activity list partial should render.
     *
     * @return 'status_changed'|'priority_changed'|'preview'|'follow_up'|'summary_line'|'json'
     */
    public static function activityTimelineMetaKind(CrmRequestActivity $activity): string
    {
        return match ($activity->type) {
            CrmRequestActivity::TYPE_STATUS_CHANGED => 'status_changed',
            CrmRequestActivity::TYPE_PRIORITY_CHANGED => 'priority_changed',
            CrmRequestActivity::TYPE_NOTE_ADDED,
            CrmRequestActivity::TYPE_SUMMARY_UPDATED => 'preview',
            CrmRequestActivity::TYPE_FOLLOW_UP_SET => 'follow_up',
            CrmRequestActivity::TYPE_INBOUND_RECEIVED,
            CrmRequestActivity::TYPE_MAIL_QUEUED,
            CrmRequestActivity::TYPE_TELEGRAM_QUEUED,
            CrmRequestActivity::TYPE_ASSIGNED => 'summary_line',
            default => 'json',
        };
    }
}
