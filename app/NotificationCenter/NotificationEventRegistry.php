<?php

namespace App\NotificationCenter;

use App\Models\Booking;
use App\Models\CrmRequest;
use App\Models\Lead;

/**
 * Central registry of notification event keys (not config-driven).
 */
final class NotificationEventRegistry
{
    /**
     * Подписка с этим event_key в БД срабатывает на любое зарегистрированное событие (см. {@see \App\NotificationCenter\NotificationRouter}).
     */
    public const WILDCARD_EVENT_KEY = '*';

    /** @var array<string, NotificationEventDefinition> */
    private static ?array $map = null;

    /**
     * @return array<string, NotificationEventDefinition>
     */
    public static function all(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $crm = 'crm';
        $booking = 'booking';
        $lead = 'lead';
        $digest = 'digest';

        self::$map = [
            'crm_request.created' => new NotificationEventDefinition(
                key: 'crm_request.created',
                subjectType: class_basename(CrmRequest::class),
                defaultSeverity: NotificationSeverity::High,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Новая заявка',
                templateClass: null,
                category: $crm,
            ),
            'lead.created' => new NotificationEventDefinition(
                key: 'lead.created',
                subjectType: class_basename(Lead::class),
                defaultSeverity: NotificationSeverity::Normal,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Новое обращение',
                templateClass: null,
                category: $lead,
            ),
            'crm_request.status_changed' => new NotificationEventDefinition(
                key: 'crm_request.status_changed',
                subjectType: class_basename(CrmRequest::class),
                defaultSeverity: NotificationSeverity::Normal,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Статус заявки изменён',
                templateClass: null,
                category: $crm,
            ),
            'crm_request.note_added' => new NotificationEventDefinition(
                key: 'crm_request.note_added',
                subjectType: class_basename(CrmRequest::class),
                defaultSeverity: NotificationSeverity::Normal,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Комментарий к заявке',
                templateClass: null,
                category: $crm,
            ),
            'crm_request.first_viewed' => new NotificationEventDefinition(
                key: 'crm_request.first_viewed',
                subjectType: class_basename(CrmRequest::class),
                defaultSeverity: NotificationSeverity::Low,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Заявка просмотрена',
                templateClass: null,
                category: $crm,
            ),
            'crm_request.follow_up_due' => new NotificationEventDefinition(
                key: 'crm_request.follow_up_due',
                subjectType: class_basename(CrmRequest::class),
                defaultSeverity: NotificationSeverity::High,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Наступило время follow-up',
                templateClass: null,
                category: $crm,
            ),
            'booking.created' => new NotificationEventDefinition(
                key: 'booking.created',
                subjectType: class_basename(Booking::class),
                defaultSeverity: NotificationSeverity::High,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Новое бронирование',
                templateClass: null,
                category: $booking,
            ),
            'booking.cancelled' => new NotificationEventDefinition(
                key: 'booking.cancelled',
                subjectType: class_basename(Booking::class),
                defaultSeverity: NotificationSeverity::Normal,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Бронирование отменено',
                templateClass: null,
                category: $booking,
            ),
            'crm_request.unviewed_5m' => new NotificationEventDefinition(
                key: 'crm_request.unviewed_5m',
                subjectType: class_basename(CrmRequest::class),
                defaultSeverity: NotificationSeverity::High,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Заявка не просмотрена (5 мин)',
                templateClass: null,
                category: $crm,
            ),
            'crm_request.unprocessed_15m' => new NotificationEventDefinition(
                key: 'crm_request.unprocessed_15m',
                subjectType: class_basename(CrmRequest::class),
                defaultSeverity: NotificationSeverity::Critical,
                supportsDigest: false,
                supportsRealtime: true,
                defaultTitle: 'Заявка без обработки (15 мин)',
                templateClass: null,
                category: $crm,
            ),
            'digest.daily_operations' => new NotificationEventDefinition(
                key: 'digest.daily_operations',
                subjectType: 'TenantDigest',
                defaultSeverity: NotificationSeverity::Digest,
                supportsDigest: true,
                supportsRealtime: false,
                defaultTitle: 'Сводка за день',
                templateClass: null,
                category: $digest,
            ),
        ];

        return self::$map;
    }

    public static function definition(string $eventKey): ?NotificationEventDefinition
    {
        return self::all()[$eventKey] ?? null;
    }

    public static function has(string $eventKey): bool
    {
        return isset(self::all()[$eventKey]);
    }

    /**
     * Ключи, разрешённые для {@see \App\Models\NotificationSubscription::$event_key} (вкл. wildcard для UI).
     */
    public static function isSubscribableEventKey(string $eventKey): bool
    {
        return $eventKey === self::WILDCARD_EVENT_KEY || self::has($eventKey);
    }

    /**
     * @return array<string, string> key => label for selects
     */
    public static function optionsForFilament(): array
    {
        $out = [
            self::WILDCARD_EVENT_KEY => 'Все уведомления (все типы событий)',
        ];
        foreach (self::all() as $def) {
            $out[$def->key] = $def->key.' — '.$def->defaultTitle;
        }

        return $out;
    }

    /**
     * Подпись в таблицах/формах для ключа (в т.ч. wildcard).
     */
    public static function labelForEventKeyInUi(string $key): string
    {
        if ($key === self::WILDCARD_EVENT_KEY) {
            return 'Все уведомления (все типы событий)';
        }

        $def = self::definition($key);

        return $def !== null ? $def->key.' — '.$def->defaultTitle : $key;
    }

    /**
     * @internal Tests only
     */
    public static function resetCacheForTesting(): void
    {
        self::$map = null;
    }
}
