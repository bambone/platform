<?php

namespace App\ContactChannels;

/**
 * Идентификаторы каналов в реестре и в JSON (registry-backed).
 */
enum ContactChannelType: string
{
    case Phone = 'phone';

    case Whatsapp = 'whatsapp';

    case Telegram = 'telegram';

    case Vk = 'vk';

    case Max = 'max';

    /** Внутренний канал предпочтения (expert_pr email-only и т.п.); не в {@see allForTenantConfig()} — не отдельная строка в настройках каналов Filament. */
    case Email = 'email';

    /**
     * Каналы, для которых хранится saved state в настройках тенанта (Filament).
     * «Email» как preferred channel обрабатывается кодом формы, без отдельного переключателя в UI каналов.
     *
     * @return list<self>
     */
    public static function allForTenantConfig(): array
    {
        return [
            self::Phone,
            self::Whatsapp,
            self::Telegram,
            self::Vk,
            self::Max,
        ];
    }

    /**
     * Каналы, которые могут быть выбраны как preferred (включая «только телефон»).
     *
     * @return list<self>
     */
    public static function preferredSelectable(): array
    {
        return [
            self::Phone,
            self::Whatsapp,
            self::Telegram,
            self::Vk,
            self::Max,
        ];
    }
}
