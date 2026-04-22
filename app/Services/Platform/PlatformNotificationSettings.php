<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use App\Support\Recipients\RecipientListParser;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-level notification provider secrets and channel kill switches (not tenant settings).
 */
final class PlatformNotificationSettings
{
    private const PREFIX = 'notification.';

    public function isChannelEnabled(string $channel): bool
    {
        $key = self::PREFIX.'channel.'.$channel.'_enabled';

        return (bool) PlatformSetting::get($key, true);
    }

    public function setChannelEnabled(string $channel, bool $enabled): void
    {
        PlatformSetting::set(self::PREFIX.'channel.'.$channel.'_enabled', $enabled, 'boolean');
    }

    /**
     * Decrypted bot token for Telegram Bot API.
     */
    public function telegramBotTokenDecrypted(): ?string
    {
        $raw = PlatformSetting::get(self::PREFIX.'telegram.bot_token', null);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return decrypt($raw);
        } catch (\Throwable) {
            return $raw;
        }
    }

    public function setTelegramBotToken(?string $tokenPlain): void
    {
        $key = self::PREFIX.'telegram.bot_token';
        if ($tokenPlain === null || $tokenPlain === '') {
            PlatformSetting::query()->where('key', $key)->delete();
            Cache::forget('platform_settings.'.$key);

            return;
        }

        PlatformSetting::set($key, encrypt($tokenPlain), 'string');
    }

    public function vapidPublicKey(): ?string
    {
        $v = PlatformSetting::get(self::PREFIX.'webpush.vapid_public', null);

        return is_string($v) && $v !== '' ? $v : null;
    }

    public function vapidPrivateKeyDecrypted(): ?string
    {
        $raw = PlatformSetting::get(self::PREFIX.'webpush.vapid_private', null);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return decrypt($raw);
        } catch (\Throwable) {
            return $raw;
        }
    }

    public function setVapidKeypair(?string $publicKey, ?string $privateKeyPlain): void
    {
        foreach (['webpush.vapid_public', 'webpush.vapid_private'] as $suffix) {
            $full = self::PREFIX.$suffix;
            PlatformSetting::query()->where('key', $full)->delete();
            Cache::forget('platform_settings.'.$full);
        }

        if ($publicKey === null || $publicKey === '') {
            return;
        }

        PlatformSetting::set(self::PREFIX.'webpush.vapid_public', $publicKey, 'string');
        if ($privateKeyPlain !== null && $privateKeyPlain !== '') {
            PlatformSetting::set(self::PREFIX.'webpush.vapid_private', encrypt($privateKeyPlain), 'string');
        }
    }

    /**
     * Remove stored VAPID public and private keys (Web Push).
     */
    public function clearVapidKeys(): void
    {
        $this->setVapidKeypair(null, null);
    }

    /**
     * Telegram chat ids for staff alerts on platform marketing contact form (raw stored; parsed on read).
     *
     * @return list<string>
     */
    public function platformContactTelegramChatIds(): array
    {
        $raw = PlatformSetting::get(self::PREFIX.'telegram.platform_contact_chat_ids', '');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return RecipientListParser::parse($raw);
    }

    public function platformContactTelegramChatIdsRaw(): string
    {
        $raw = PlatformSetting::get(self::PREFIX.'telegram.platform_contact_chat_ids', '');

        return is_string($raw) ? $raw : '';
    }

    public function setPlatformContactTelegramChatIds(?string $value): void
    {
        $key = self::PREFIX.'telegram.platform_contact_chat_ids';
        if ($value === null || trim($value) === '') {
            PlatformSetting::query()->where('key', $key)->delete();
            Cache::forget('platform_settings.'.$key);

            return;
        }

        PlatformSetting::set($key, trim($value), 'string');
    }
}
