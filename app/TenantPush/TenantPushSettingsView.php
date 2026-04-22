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
        public TenantPushGuidedSetupState $guidedSetup,
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

        $guided = TenantPushGuidedSetupState::make($tenant, $gate, $settings, $pref, null);

        return new self($gate, $settings, $aggregate, $ready, $crmEnabled, $total, $active, $guided);
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
            return 'По текущему тарифу или коммерческим настройкам доставка push не активна. Раздел можно смотреть, но тесты и события на сайт не пойдут, пока платформа не подключит функцию.';
        }

        if (! $this->crmEventEnabled) {
            $g = $this->guidedSetup;
            if ($g->primaryReason !== TenantPushGuidedSetupReason::None) {
                return 'Сначала завершите настройку push: '.$g->primaryReason->userMessage();
            }

            return 'Включите ниже уведомление о новой заявке и сохраните — тогда при обращении с сайта сможет уходить push к выбранным сотрудникам (если у них подписка в браузере).';
        }

        if ($this->targetRecipientCount === 0) {
            return 'Нужны получатели: владелец, команда и/или выбранные сотрудники с доступом. Укажите «кому», сохраните и проверьте подписки в браузере.';
        }

        if ($this->settings->providerStatusEnum() !== TenantPushProviderStatus::Verified) {
            return 'Сначала подтвердите ключи OneSignal кнопкой «Проверить OneSignal» на странице PWA и Push.';
        }

        if ($this->activeSubscriptionCount === 0) {
            return 'Ключи в порядке, но у выбранных сотрудников нет активной push-подписки: зайдите в кабинет с устройства и разрешите уведомления. На iPhone откройте сайт с иконки на «Домой».';
        }

        if ($this->subscriptionAggregate === TenantPushSubscriptionAggregate::Partial) {
            return 'События доставляются, но у части выбранных сотрудников нет push-подписки в браузере — войдите за них и примите уведомления.';
        }

        return 'Готово: OneSignal подтверждён и у целевых сотрудников есть хотя бы одна активная подписка.';
    }
}
