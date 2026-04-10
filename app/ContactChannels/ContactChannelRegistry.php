<?php

namespace App\ContactChannels;

/**
 * Метаданные каналов: подписи, иконки, порядок по умолчанию, нужен ли ввод от посетителя.
 */
final class ContactChannelRegistry
{
    /**
     * @return array<string, array{label: string, icon: string, default_sort: int, requires_visitor_value: bool, filament_action_color: string}>
     */
    public static function definitions(): array
    {
        return [
            ContactChannelType::Phone->value => [
                'label' => 'Телефон',
                'icon' => 'heroicon-o-phone',
                'default_sort' => 10,
                'requires_visitor_value' => false,
                'filament_action_color' => 'gray',
            ],
            ContactChannelType::Whatsapp->value => [
                'label' => 'WhatsApp',
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'default_sort' => 20,
                'requires_visitor_value' => false,
                'filament_action_color' => 'success',
            ],
            ContactChannelType::Telegram->value => [
                'label' => 'Telegram',
                'icon' => 'heroicon-o-paper-airplane',
                'default_sort' => 30,
                'requires_visitor_value' => true,
                'filament_action_color' => 'info',
            ],
            ContactChannelType::Vk->value => [
                'label' => 'ВКонтакте',
                'icon' => 'heroicon-o-user-group',
                'default_sort' => 40,
                'requires_visitor_value' => true,
                'filament_action_color' => 'primary',
            ],
            ContactChannelType::Max->value => [
                'label' => 'MAX',
                'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis',
                'default_sort' => 50,
                'requires_visitor_value' => true,
                'filament_action_color' => 'warning',
            ],
        ];
    }

    public static function label(string $type): string
    {
        if ($type === 'email') {
            return 'Email';
        }

        return self::definitions()[$type]['label'] ?? $type;
    }

    public static function defaultSort(string $type): int
    {
        return self::definitions()[$type]['default_sort'] ?? 99;
    }

    public static function requiresVisitorValue(string $type): bool
    {
        return self::definitions()[$type]['requires_visitor_value'] ?? false;
    }

    /**
     * Подсказка для публичной формы: что вводить в поле контакта (RU).
     * В браузере нельзя надёжно «подтянуть» ник из VK/Telegram/MAX без отдельной OAuth-интеграции.
     */
    public static function visitorValueHintRu(string $type): string
    {
        return match ($type) {
            ContactChannelType::Telegram->value => 'Введите username (например ivan_ivanov или @ivan_ivanov) или ссылку вида t.me/username. Приложение Telegram не передаёт сайту ваш ник — скопируйте его из «Настройки → Имя пользователя».',
            ContactChannelType::Vk->value => 'Вставьте ссылку на профиль: https://vk.com/id123 или https://vk.com/nickname. Короткий id или ник после vk.com/ тоже подойдут — мы сохраним канонический адрес. Выбрать профиль из приложения VK на сайте нельзя; откройте свой профиль и скопируйте URL из адресной строки.',
            ContactChannelType::Max->value => 'Укажите ссылку из мессенджера MAX, если есть, или любой понятный текст для связи. Автоподстановка из приложения в обычной веб-форме недоступна.',
            default => '',
        };
    }

    public static function visitorValuePlaceholderRu(string $type): string
    {
        return match ($type) {
            ContactChannelType::Telegram->value => '@username или t.me/username',
            ContactChannelType::Vk->value => 'https://vk.com/… или id123456',
            ContactChannelType::Max->value => 'Ссылка или текст для связи в MAX',
            default => '',
        };
    }
}
