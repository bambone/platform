<?php

declare(strict_types=1);

namespace App\TenantPush;

/**
 * Machine reason for why a step is blocked or what to do next (tests, save, UI).
 */
enum TenantPushGuidedSetupReason: string
{
    case None = 'none';
    case FeatureNotEntitled = 'feature_not_entitled';
    case NoActiveDomain = 'no_active_domain';
    case DomainNotSelected = 'domain_not_selected';
    case SslNotReady = 'ssl_not_ready';
    case OneSignalAppIdMissing = 'onesignal_app_id_missing';
    case OneSignalApiKeyMissing = 'onesignal_api_key_missing';
    case OneSignalNotVerified = 'onesignal_not_verified';
    case PushSendingNotEnabled = 'push_sending_not_enabled';
    case CrmRecipientsMissing = 'crm_recipients_missing';

    public function userMessage(): string
    {
        return match ($this) {
            self::None => '',
            self::FeatureNotEntitled => 'Сначала подключите push-услугу на платформе (тариф и коммерческая активация).',
            self::NoActiveDomain => 'Подключите и активируйте домен в разделе доменов.',
            self::DomainNotSelected => 'Выберите основной HTTPS-домен для push.',
            self::SslNotReady => 'Для выбранного домена ещё не готов SSL-сертификат. Браузерные push работают только через HTTPS.',
            self::OneSignalAppIdMissing => 'Укажите App ID из кабинета OneSignal.',
            self::OneSignalApiKeyMissing => 'Укажите и сохраните App API Key OneSignal.',
            self::OneSignalNotVerified => 'Выполните проверку подключения к OneSignal (кнопка «Проверить OneSignal»).',
            self::PushSendingNotEnabled => 'Сначала включите отправку push через OneSignal и сохраните настройки.',
            self::CrmRecipientsMissing => 'Выберите получателей уведомлений о новых заявках.',
        };
    }
}
