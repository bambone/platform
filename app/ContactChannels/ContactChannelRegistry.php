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
            ContactChannelType::Email->value => [
                'label' => 'Email',
                'icon' => 'heroicon-o-envelope',
                'default_sort' => 45,
                'requires_visitor_value' => true,
                'filament_action_color' => 'gray',
            ],
        ];
    }

    public static function label(string $type): string
    {
        return self::definitions()[$type]['label'] ?? $type;
    }

    /**
     * English labels on public-facing expert forms (`tenant.locale` en*).
     */
    public static function labelEnPublic(string $type): string
    {
        return match ($type) {
            ContactChannelType::Phone->value => 'Phone',
            ContactChannelType::Whatsapp->value => 'WhatsApp',
            ContactChannelType::Telegram->value => 'Telegram',
            ContactChannelType::Vk->value => 'VK',
            ContactChannelType::Max->value => 'MAX',
            ContactChannelType::Email->value => 'Email',
            default => self::label($type),
        };
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
     * @see visitorValuePlaceholderRu(); keep Latin examples compatible with Telegram/VK handles.
     */
    public static function visitorValuePlaceholderEn(string $type): string
    {
        return match ($type) {
            ContactChannelType::Telegram->value => '@username / t.me/username',
            ContactChannelType::Vk->value => 'e.g. vk.com/username',
            ContactChannelType::Max->value => 'MAX link or other contact hint',
            default => '',
        };
    }

    /**
     * @see visitorValueFieldLabelRu()
     */
    public static function visitorValueFieldLabelEn(string $type): string
    {
        return match ($type) {
            ContactChannelType::Vk->value => 'VK profile link or username',
            ContactChannelType::Telegram->value => 'Telegram link or @username',
            ContactChannelType::Max->value => 'MAX contact',
            default => '',
        };
    }

    /**
     * English hints mirroring {@see visitorValueHintRu()}
     */
    public static function visitorValueHintEn(string $type): string
    {
        return match ($type) {
            ContactChannelType::Telegram->value => 'Telegram username: latin letters, digits and underscores (typically 5–32 chars), or a https://t.me/… URL. Copy it from Telegram: Settings → Username.',
            ContactChannelType::Vk->value => 'Paste your VK profile URL or short name. We will normalise it to https://vk.com/…',
            ContactChannelType::Max->value => 'Add a MAX link if you have one, or short free text explaining how to reach you in MAX.',
            default => '',
        };
    }

    /**
     * Подсказка для публичной формы: что вводить в поле контакта (RU).
     * В браузере нельзя надёжно «подтянуть» ник из VK/Telegram/MAX без отдельной OAuth-интеграции.
     *
     * Placeholder (visitorValuePlaceholderRu) держим с латинскими примерами: ник Telegram/VK — ASCII;
     * пояснения на русском только здесь и в сообщениях валидации.
     */
    public static function visitorValueHintRu(string $type): string
    {
        return match ($type) {
            ContactChannelType::Telegram->value => 'Ник в Telegram — только латиница, цифры и подчёркивание (5–32 символа), либо ссылка https://t.me/… Скопируйте username в приложении: Настройки → Имя пользователя.',
            ContactChannelType::Vk->value => 'Укажите ссылку на ваш профиль VK или короткое имя (ник). Мы сохраним канонический адрес https://vk.com/… Скопируйте URL из браузера или введите ник без лишнего текста.',
            ContactChannelType::Max->value => 'Укажите ссылку из мессенджера MAX, если есть, или любой понятный текст для связи. Автоподстановка из приложения в обычной веб-форме недоступна.',
            default => '',
        };
    }

    public static function visitorValuePlaceholderRu(string $type): string
    {
        return match ($type) {
            ContactChannelType::Telegram->value => '@username / t.me/username',
            ContactChannelType::Vk->value => 'Например: vk.com/username',
            ContactChannelType::Max->value => 'Ссылка или текст для связи в MAX',
            default => '',
        };
    }

    /**
     * Подпись поля контакта на публичной форме (когда канал требует отдельного value).
     */
    public static function visitorValueFieldLabelRu(string $type): string
    {
        return match ($type) {
            ContactChannelType::Vk->value => 'Ссылка или username VK',
            ContactChannelType::Telegram->value => 'Ссылка или username Telegram',
            ContactChannelType::Max->value => 'Контакт в MAX',
            default => '',
        };
    }
}
