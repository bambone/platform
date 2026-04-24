<?php

namespace App\Auth;

use App\Providers\AppServiceProvider;

/**
 * Canonical ability names evaluated for the tenant Filament panel via Gate::before.
 *
 * @see AppServiceProvider
 */
final class TenantAbilityRegistry
{
    /**
     * @return list<string>
     */
    public static function abilities(): array
    {
        return array_keys(self::labels());
    }

    /**
     * @return array<string, string> ability => short Russian label for UI
     */
    public static function labels(): array
    {
        return [
            'manage_users' => 'Пользователи кабинета',
            'manage_roles' => 'Роли и права (Spatie)',
            'manage_settings' => 'Настройки сайта',
            'manage_terminology' => 'Терминология и названия',
            'manage_seo_files' => 'SEO-файлы (robots, sitemap)',
            'manage_seo' => 'SEO',
            'manage_pages' => 'Страницы',
            'manage_homepage' => 'Главная страница',
            'manage_motorcycles' => 'Каталог / техника',
            'manage_leads' => 'Заявки',
            'export_leads' => 'Экспорт заявок',
            'manage_bookings' => 'Бронирования',
            'manage_reviews' => 'Отзывы',
            'manage_faq' => 'FAQ',
            'manage_contacts' => 'Контакты',
            'manage_media' => 'Медиа',
            'manage_tenant_files' => 'Файлы в storage (просмотр и удаление)',
            'manage_integrations' => 'Интеграции',
            'manage_notifications' => 'Уведомления (настройки и каналы)',
            'view_notification_history' => 'История уведомлений',
            'manage_notification_destinations' => 'Получатели уведомлений',
            'manage_notification_subscriptions' => 'Правила уведомлений',
            'manage_push_devices' => 'Push-устройства',
            'manage_scheduling' => 'Запись и расписание (scheduling)',
        ];
    }

    public static function isValidAbility(string $ability): bool
    {
        return array_key_exists($ability, self::labels());
    }

    /**
     * @param  list<string>|mixed  $abilities
     * @return list<string>
     */
    public static function onlyRegistered(mixed $abilities): array
    {
        if (! is_array($abilities)) {
            return [];
        }

        $allowed = self::abilities();

        return array_values(array_unique(array_values(array_filter(
            $abilities,
            static fn (mixed $a): bool => is_string($a) && in_array($a, $allowed, true)
        ))));
    }
}
