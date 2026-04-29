<?php

namespace App\ContactChannels;

/**
 * Единые тексты валидации preferred_contact_value для публичных форм (RU).
 */
final class PreferredContactValueMessages
{
    public static function requiredRu(string $channelId): string
    {
        return match ($channelId) {
            ContactChannelType::Vk->value => 'Укажите контакт VK, чтобы мы могли связаться с вами этим способом.',
            ContactChannelType::Telegram->value => 'Укажите контакт Telegram, чтобы мы могли связаться с вами этим способом.',
            ContactChannelType::Max->value => 'Укажите контакт MAX, чтобы мы могли связаться с вами этим способом.',
            default => 'Укажите контакт для выбранного способа связи.',
        };
    }

    public static function invalidFormatRu(string $channelId): string
    {
        return match ($channelId) {
            ContactChannelType::Vk->value => 'Укажите ссылку на профиль VK или короткое имя (ник), например vk.com/username.',
            ContactChannelType::Telegram->value => 'Укажите корректный Telegram (username или ссылка https://t.me/…).',
            ContactChannelType::Max->value => 'Укажите контакт MAX (текст или ссылку).',
            default => 'Проверьте контакт для выбранного способа связи.',
        };
    }

    public static function required(string $channelId, bool $english): string
    {
        return $english ? match ($channelId) {
            ContactChannelType::Vk->value => 'Enter your VK username or profile link so we can reach you.',
            ContactChannelType::Telegram->value => 'Enter your Telegram username so we can reach you.',
            ContactChannelType::Max->value => 'Enter your MAX contact so we can reach you.',
            ContactChannelType::Email->value => 'Enter the email address you want us to reply to.',
            default => 'Enter details for your selected contact method.',
        } : self::requiredRu($channelId);
    }

    public static function invalidFormat(string $channelId, bool $english): string
    {
        return $english ? match ($channelId) {
            ContactChannelType::Vk->value => 'Use a VK profile URL or username (e.g. vk.com/username).',
            ContactChannelType::Telegram->value => 'Use a Telegram username or a https://t.me/… link.',
            ContactChannelType::Max->value => 'Check your MAX username or paste a valid MAX link.',
            ContactChannelType::Email->value => 'Enter a valid email address.',
            default => 'Check the contact detail for your selected channel.',
        } : self::invalidFormatRu($channelId);
    }
}
