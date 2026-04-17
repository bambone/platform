<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Models\Tenant;
use App\Models\TenantOnesignalPushIdentity;
use App\Models\TenantPushEventPreference;
use App\Models\TenantPushSettings;

final readonly class TenantPushSettingsView
{
    public function __construct(
        public TenantPushGateResult $gate,
        public TenantPushSettings $settings,
        public TenantPushSubscriptionAggregate $subscriptionAggregate,
        public bool $readyForEventDelivery,
        public bool $crmEventEnabled,
        public int $targetRecipientCount,
        public int $activeSubscriptionCount,
    ) {}

    public static function make(Tenant $tenant, TenantPushFeatureGate $featureGate, TenantPushCrmRequestRecipientResolver $recipientResolver): self
    {
        $gate = $featureGate->evaluate($tenant);
        $settings = $featureGate->resolveSettingsForDisplay($tenant);

        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();

        $targetIds = $recipientResolver->resolveOnesignalRecipientUserIds($tenant);
        $total = count($targetIds);
        $active = 0;
        if ($targetIds !== []) {
            $active = TenantOnesignalPushIdentity::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->whereIn('user_id', $targetIds)
                ->pluck('user_id')
                ->unique()
                ->count();
        }

        $crmEnabled = $pref !== null && $pref->is_enabled;

        $aggregate = match (true) {
            $total === 0 || ! $crmEnabled => TenantPushSubscriptionAggregate::None,
            $active === 0 => TenantPushSubscriptionAggregate::None,
            $active >= $total => TenantPushSubscriptionAggregate::Active,
            default => TenantPushSubscriptionAggregate::Partial,
        };

        $providerOk = $settings->providerStatusEnum() === TenantPushProviderStatus::Verified;
        $ready = $providerOk
            && $crmEnabled
            && $total > 0
            && $active >= 1;

        return new self($gate, $settings, $aggregate, $ready, $crmEnabled, $total, $active);
    }

    public function providerStatusLabel(): string
    {
        return match ($this->settings->providerStatusEnum()) {
            TenantPushProviderStatus::NotConfigured => 'Не настроен',
            TenantPushProviderStatus::Invalid => 'Ошибка проверки ключей',
            TenantPushProviderStatus::Verified => 'Проверен (ключи приложения)',
        };
    }

    public function subscriptionStatusLabel(): string
    {
        return match ($this->subscriptionAggregate) {
            TenantPushSubscriptionAggregate::None => 'Нет (ни у кого из целевых нет активной привязки OneSignal)',
            TenantPushSubscriptionAggregate::Partial => 'Частично (есть подписка не у всех выбранных)',
            TenantPushSubscriptionAggregate::Active => 'Полная (у всех целевых есть активная привязка)',
        };
    }

    public function readinessHint(): string
    {
        if (! $this->gate->isFeatureEntitled()) {
            return 'Доставка по событиям недоступна: нет права на функцию (тариф / коммерция или отключение платформой). Настройки только для просмотра.';
        }

        if (! $this->crmEventEnabled) {
            return 'Включите push для события «Новая заявка» ниже и сохраните — иначе маршрутизация OneSignal не используется.';
        }

        if ($this->targetRecipientCount === 0) {
            return 'Не выбраны получатели для этого события (владелец / команда). Укажите, кому слать.';
        }

        if ($this->settings->providerStatusEnum() !== TenantPushProviderStatus::Verified) {
            return 'Провайдер OneSignal не подтверждён: выполните проверку ключей. Подписки получателей учитываются отдельно от статуса ключей.';
        }

        if ($this->activeSubscriptionCount === 0) {
            return 'Провайдер проверен, но у целевых получателей нет активной подписки OneSignal: войдите в кабинет с устройства, разрешите уведомления (и на iOS откройте сайт с иконки на главном экране).';
        }

        if ($this->subscriptionAggregate === TenantPushSubscriptionAggregate::Partial) {
            return 'События могут доставляться, но не всем выбранным получателям — у части нет подписки OneSignal.';
        }

        return 'Готово: провайдер проверен и есть хотя бы одна активная подписка среди целевых получателей.';
    }
}
