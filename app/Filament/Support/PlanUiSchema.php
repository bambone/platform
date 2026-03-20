<?php

namespace App\Filament\Support;

/**
 * Известные ключи лимитов и функций тарифа для человекочитаемой формы (данные остаются в JSON в БД).
 */
final class PlanUiSchema
{
    /**
     * @return array<string, string> feature key => label
     */
    public static function featureOptions(): array
    {
        return [
            'cms' => 'Конструктор страниц (CMS)',
            'catalog' => 'Каталог техники',
            'leads' => 'Заявки и лиды',
            'seo' => 'SEO-настройки',
            'booking_engine' => 'Онлайн-бронирование',
            'custom_domain' => 'Свой домен',
        ];
    }

    /**
     * Ключи лимитов с подписями и суффиксами для формы.
     *
     * @return array<string, array{label: string, helper: string, suffix: ?string}>
     */
    public static function limitFields(): array
    {
        return [
            'max_models' => [
                'label' => 'Максимум карточек в каталоге',
                'helper' => 'Сколько моделей техники клиент может вести в каталоге по этому тарифу.',
                'suffix' => null,
            ],
            'max_leads_per_month' => [
                'label' => 'Максимум заявок в месяц',
                'helper' => 'Ограничение на количество входящих заявок за календарный месяц.',
                'suffix' => null,
            ],
        ];
    }
}
