<?php

namespace App\Product\Settings;

use App\Models\PlatformSetting;
use App\Support\Recipients\RecipientListParser;

/**
 * Чтение platform-level почтовых и брендовых настроек для продукта (не SMTP-транспорт).
 *
 * Централизует ключи email.* / platform_name; UI и Mailable не должны разбрасывать PlatformSetting::get().
 */
final class ProductMailSettingsResolver
{
    public function platformBrandName(): string
    {
        return (string) PlatformSetting::get(
            'platform_name',
            config('platform_marketing.brand_name', config('app.name'))
        );
    }

    public function defaultFromAddress(): string
    {
        if (config('mail.use_smtp_user_as_platform_from', false)) {
            $smtp = $this->smtpUsernameAsEmail();
            if ($smtp !== '') {
                return $smtp;
            }
        }

        $from = trim((string) PlatformSetting::get('email.default_from_address', ''));
        if ($from === '') {
            $from = trim((string) config('mail.from.address', ''));
        }
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }

        $smtp = $this->smtpUsernameAsEmail();
        if ($smtp !== '') {
            return $smtp;
        }

        return $from;
    }

    private function smtpUsernameAsEmail(): string
    {
        $u = trim((string) config('mail.mailers.smtp.username', ''));

        if ($u === '' || filter_var($u, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return $u;
    }

    public function defaultFromName(): string
    {
        $brand = $this->platformBrandName();

        return (string) PlatformSetting::get('email.default_from_name', $brand);
    }

    /**
     * Получатели уведомлений по маркетинговой форме контактов (платформа).
     *
     * @return list<string>
     */
    public function resolvePlatformContactRecipients(): array
    {
        $raw = PlatformSetting::get('email.contact_form_recipients', '');
        if (is_string($raw) && trim($raw) !== '') {
            $parsed = RecipientListParser::parse($raw);
            if ($parsed !== []) {
                return $parsed;
            }
        }

        $envTo = trim((string) config('platform_marketing.contact_mail_to', ''));
        if ($envTo !== '') {
            return RecipientListParser::parse($envTo);
        }

        $from = trim((string) config('mail.from.address', ''));

        return $from !== '' ? [$from] : [];
    }
}
