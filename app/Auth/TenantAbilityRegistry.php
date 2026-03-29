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
            'manage_integrations' => 'Интеграции',
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
