<?php

namespace App\Filament\Support;

/**
 * Реестр известных ключей platform_settings: человекочитаемый UI и группировка.
 * Произвольные ключи остаются допустимыми в режиме «Для разработчика».
 */
final class PlatformSettingRegistry
{
    public const GROUP_GENERAL = 'Общие настройки платформы';

    public const GROUP_DOMAINS = 'Домены и адреса';

    public const GROUP_EMAIL = 'Email и уведомления';

    public const GROUP_BILLING = 'Биллинг';

    public const GROUP_INTEGRATIONS = 'Интеграции';

    public const GROUP_BEHAVIOR = 'Поведение платформы';

    public const GROUP_TECHNICAL = 'Технические параметры';

    public const GROUP_MARKETING = 'Маркетинг и лендинг';

    public const GROUP_STORAGE = 'Хранилище и квоты';

    /**
     * @return array<string, array{group: string, label: string, description: string, type: 'string'|'integer'|'boolean'|'json'}>
     */
    public static function definitions(): array
    {
        return [
            'platform_name' => [
                'group' => self::GROUP_GENERAL,
                'label' => 'Название платформы',
                'description' => 'Отображается в письмах и служебных экранах, где нужно имя продукта.',
                'type' => 'string',
            ],
            'platform_support_email' => [
                'group' => self::GROUP_EMAIL,
                'label' => 'Email поддержки платформы',
                'description' => 'Адрес для обращений пользователей и системных уведомлений, связанных с поддержкой.',
                'type' => 'string',
            ],
            'platform_noreply_email' => [
                'group' => self::GROUP_EMAIL,
                'label' => 'Email отправителя (no-reply)',
                'description' => 'С какого адреса уходят автоматические письма (если используется приложением).',
                'type' => 'string',
            ],
            'default_tenant_plan_slug' => [
                'group' => self::GROUP_BEHAVIOR,
                'label' => 'Тариф по умолчанию для новых клиентов',
                'description' => 'URL-идентификатор тарифа (slug), который подставляется при создании клиента без выбора тарифа.',
                'type' => 'string',
            ],
            'maintenance_mode' => [
                'group' => self::GROUP_TECHNICAL,
                'label' => 'Режим обслуживания',
                'description' => 'Если включено — публичные сценарии могут показывать заглушку (зависит от реализации приложения).',
                'type' => 'boolean',
            ],
            'email.contact_form_recipients' => [
                'group' => self::GROUP_EMAIL,
                'label' => 'Получатели уведомлений по форме контактов',
                'description' => 'Адреса через запятую или JSON-массив. Используется для входящих с маркетингового сайта (CRM + почта).',
                'type' => 'string',
            ],
            'email.default_from_address' => [
                'group' => self::GROUP_EMAIL,
                'label' => 'From (адрес) для продуктовых писем платформы',
                'description' => 'Отправитель служебных писем с маркетинговых форм; fallback — mail.from.',
                'type' => 'string',
            ],
            'email.default_from_name' => [
                'group' => self::GROUP_EMAIL,
                'label' => 'From (имя) для продуктовых писем платформы',
                'description' => 'Отображаемое имя отправителя; fallback — platform_name / бренд.',
                'type' => 'string',
            ],
            'marketing.config_overlay' => [
                'group' => self::GROUP_MARKETING,
                'label' => 'Оверлей контента лендинга (JSON)',
                'description' => 'Сливается поверх config/platform_marketing.php. Редактирование удобнее на странице «Маркетинг и контент».',
                'type' => 'json',
            ],
            'tenant_storage.default_base_quota_bytes' => [
                'group' => self::GROUP_STORAGE,
                'label' => 'Базовая квота хранилища для новых клиентов (байты)',
                'description' => 'Например 104857600 = 100 МБ. Применяется при создании записи квоты.',
                'type' => 'integer',
            ],
            'tenant_storage.default_warning_threshold_percent' => [
                'group' => self::GROUP_STORAGE,
                'label' => 'Порог предупреждения по остатку (%)',
                'description' => 'Доля свободного места, ниже которой статус warning_20.',
                'type' => 'integer',
            ],
            'tenant_storage.default_critical_threshold_percent' => [
                'group' => self::GROUP_STORAGE,
                'label' => 'Критический порог по остатку (%)',
                'description' => 'Доля свободного места, ниже которой статус critical_10.',
                'type' => 'integer',
            ],
            'tenant_storage.default_hard_stop_enabled' => [
                'group' => self::GROUP_STORAGE,
                'label' => 'Жёсткая блокировка загрузок по умолчанию',
                'description' => 'Для новых записей квоты: блокировать загрузки при переполнении.',
                'type' => 'boolean',
            ],
            'tenant_storage.tenant_expansion_hint' => [
                'group' => self::GROUP_STORAGE,
                'label' => 'Текст для клиента: как расширить хранилище',
                'description' => 'Показывается в кабинете клиента на странице «Мониторинг и лимиты».',
                'type' => 'string',
            ],
            'platform_storage.account_capacity_bytes' => [
                'group' => self::GROUP_STORAGE,
                'label' => 'Ёмкость объектного хранилища (байты, опционально)',
                'description' => 'Ручное значение для дашборда платформы (R2/аккаунт). Не влияет на квоты клиентов.',
                'type' => 'integer',
            ],
        ];
    }

    public static function definition(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::definitions()[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::definition($key)['label'] ?? $key;
    }

    public static function group(string $key): string
    {
        return self::definition($key)['group'] ?? self::GROUP_TECHNICAL;
    }

    /**
     * @return list<string>
     */
    public static function knownKeys(): array
    {
        return array_keys(self::definitions());
    }
}
