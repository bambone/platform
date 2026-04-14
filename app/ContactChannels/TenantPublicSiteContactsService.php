<?php

namespace App\ContactChannels;

use App\Models\Tenant;
use App\Models\TenantSetting;

/**
 * Публичные контакты для Blade (шапка, плавающие кнопки): из contact_channels.state, без чужих дефолтов.
 * Данные всегда по {@see Tenant::$id} в {@see TenantSetting}; шаблоны общие — изоляция здесь и в View-composer.
 *
 * Тесты: {@see \Tests\Unit\ContactChannels\TenantPublicSiteContactsServiceTest},
 * {@see \Tests\Feature\Tenant\TenantPublicContactsComposerIsolationTest}.
 */
final class TenantPublicSiteContactsService
{
    public function __construct(
        private TenantContactChannelsStore $channels,
    ) {}

    /**
     * Плавающие кнопки WA/TG на лендинге: по умолчанию включены, пока явно не выключены в настройках.
     */
    public function floatingMessengerButtonsEnabled(int $tenantId): bool
    {
        $v = TenantSetting::getForTenant($tenantId, 'public_site.floating_messenger_buttons', true);

        if (is_bool($v)) {
            return $v;
        }

        if (is_string($v)) {
            $t = strtolower(trim($v));

            return match ($t) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => filter_var($v, FILTER_VALIDATE_BOOLEAN),
            };
        }

        return (bool) $v;
    }

    /**
     * @return array{phone: string, phone_alt: string, email: string, whatsapp: string, telegram: string, vk_url: string}
     */
    public function contactsForPublicLayout(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->id;
        $state = $this->channels->resolvedState($tenantId);
        $hasSavedChannels = TenantContactChannelsStore::hasSavedContactChannelsState($tenantId);

        $phone = $this->resolvePhoneForLayout($state, $hasSavedChannels, $tenantId);

        return [
            'phone' => $phone,
            'phone_alt' => trim((string) TenantSetting::getForTenant($tenantId, 'contacts.phone_alt', '')),
            'email' => trim((string) TenantSetting::getForTenant($tenantId, 'contacts.email', '')),
            'whatsapp' => $this->resolveWhatsappDigits($state),
            'telegram' => $this->resolveTelegramHandle($state),
            'vk_url' => $this->resolveVkUrl($state),
        ];
    }

    /**
     * Быстрые кнопки (угол экрана): канал с контактом, если команда его использует и он либо публичный на сайте, либо доступен в формах заявок.
     */
    private function floatingMessengerChannelUsable(?TenantContactChannelConfig $cfg): bool
    {
        if ($cfg === null || ! $cfg->usesChannel) {
            return false;
        }

        if (trim($cfg->businessValue) === '') {
            return false;
        }

        return $cfg->publicVisible || $cfg->allowedInForms;
    }

    /**
     * @param  array<string, TenantContactChannelConfig>  $state
     */
    private function resolvePhoneForLayout(array $state, bool $hasSavedChannels, int $tenantId): string
    {
        $p = $state[ContactChannelType::Phone->value] ?? null;
        if ($p === null || ! $p->usesChannel) {
            return '';
        }

        $value = trim($p->businessValue);
        if ($value === '') {
            return '';
        }

        if ($p->publicVisible || ! $hasSavedChannels) {
            return $value;
        }

        return '';
    }

    /**
     * @param  array<string, TenantContactChannelConfig>  $state
     */
    private function resolveWhatsappDigits(array $state): string
    {
        $w = $state[ContactChannelType::Whatsapp->value] ?? null;
        if ($w === null || ! $this->floatingMessengerChannelUsable($w)) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $w->businessValue) ?? '';

        return $digits !== '' ? $digits : '';
    }

    /**
     * @param  array<string, TenantContactChannelConfig>  $state
     */
    private function resolveTelegramHandle(array $state): string
    {
        $t = $state[ContactChannelType::Telegram->value] ?? null;
        if ($t === null || ! $this->floatingMessengerChannelUsable($t)) {
            return '';
        }

        $h = ltrim(trim($t->businessValue), '@');

        return $h;
    }

    /**
     * @param  array<string, TenantContactChannelConfig>  $state
     */
    private function resolveVkUrl(array $state): string
    {
        $v = $state[ContactChannelType::Vk->value] ?? null;
        if ($v === null || ! $this->floatingMessengerChannelUsable($v)) {
            return '';
        }

        return VisitorContactNormalizer::normalizeVk($v->businessValue) ?? '';
    }
}
