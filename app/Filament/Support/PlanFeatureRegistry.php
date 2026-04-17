<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\TenantPush\TenantPushFeature;

/**
 * Единый реестр ключей фич тарифа: ключ, подпись, описание, группа для UI.
 */
final class PlanFeatureRegistry
{
    /**
     * @return array<string, array{label: string, description: string, group: string}>
     */
    public static function definitions(): array
    {
        return [
            'cms' => [
                'label' => 'Конструктор страниц (CMS)',
                'description' => 'Редактор страниц и блоков публичного сайта.',
                'group' => 'Контент',
            ],
            'catalog' => [
                'label' => 'Каталог техники',
                'description' => 'Карточки техники и связанные разделы.',
                'group' => 'Каталог',
            ],
            'leads' => [
                'label' => 'Заявки и лиды',
                'description' => 'Входящие заявки и работа с лидами.',
                'group' => 'CRM',
            ],
            'seo' => [
                'label' => 'SEO-настройки',
                'description' => 'Мета-теги, структура, индексация.',
                'group' => 'Маркетинг',
            ],
            'booking_engine' => [
                'label' => 'Онлайн-бронирование',
                'description' => 'Бронирование техники через сайт.',
                'group' => 'Бронирование',
            ],
            'custom_domain' => [
                'label' => 'Свой домен',
                'description' => 'Подключение собственного домена и SSL.',
                'group' => 'Инфраструктура',
            ],
            TenantPushFeature::WEB_PUSH_ONESIGNAL => [
                'label' => 'OneSignal Web Push и PWA',
                'description' => 'Push-уведомления через OneSignal и PWA (установка на экран).',
                'group' => 'Уведомления',
            ],
        ];
    }

    /**
     * @return array<string, string> key => label (для CheckboxList тарифа)
     */
    public static function featureOptions(): array
    {
        return collect(self::definitions())->map(fn (array $d): string => $d['label'])->all();
    }

    public static function label(string $key): ?string
    {
        return self::definitions()[$key]['label'] ?? null;
    }

    public static function description(string $key): ?string
    {
        return self::definitions()[$key]['description'] ?? null;
    }

    public static function group(string $key): ?string
    {
        return self::definitions()[$key]['group'] ?? null;
    }
}
