<?php

namespace App\ContactChannels;

use App\Models\Tenant;
use App\Models\TenantSetting;

/**
 * Чтение/запись contact_channels.state + bootstrap из legacy contacts.*.
 */
class TenantContactChannelsStore
{
    public const SETTING_KEY = 'contact_channels.state';

    public static function hasSavedContactChannelsState(int $tenantId): bool
    {
        return TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('group', 'contact_channels')
            ->where('key', 'state')
            ->exists();
    }

    /**
     * @return array<string, TenantContactChannelConfig>
     */
    public function resolvedState(int $tenantId): array
    {
        $hasSavedState = self::hasSavedContactChannelsState($tenantId);

        $stored = TenantSetting::getForTenant($tenantId, self::SETTING_KEY, null);
        $map = is_array($stored) ? $stored : [];

        $out = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $key = $type->value;
            $defaults = $this->defaultRowForType($type);
            if (isset($map[$key]) && is_array($map[$key])) {
                $merged = array_merge($defaults, $map[$key]);
                $out[$key] = TenantContactChannelConfig::fromArray($merged);
            } else {
                $out[$key] = TenantContactChannelConfig::fromArray($defaults);
            }
        }

        if (! $hasSavedState) {
            $out = $this->applyBootstrapFromLegacyContacts($tenantId, $out);
        }

        $p = $out[ContactChannelType::Phone->value];
        if (! $p->usesChannel) {
            $out[ContactChannelType::Phone->value] = new TenantContactChannelConfig(
                usesChannel: true,
                publicVisible: $p->publicVisible,
                allowedInForms: $p->allowedInForms,
                businessValue: $p->businessValue,
                sortOrder: $p->sortOrder,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, TenantContactChannelConfig>  $state
     * @param  array<string, array<string, mixed>>  $rawMap
     */
    public function persist(int $tenantId, array $rawMap): void
    {
        $clean = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $key = $type->value;
            $row = $rawMap[$key] ?? [];
            $defaults = $this->defaultRowForType($type);
            $merged = array_merge($defaults, is_array($row) ? $row : []);
            $clean[$key] = TenantContactChannelConfig::fromArray($merged)->toArray();
        }

        TenantSetting::setForTenant($tenantId, self::SETTING_KEY, $clean, 'json');
    }

    /**
     * Типы, которые можно выбрать как preferred на публичной форме (включая phone).
     *
     * @return list<string>
     */
    public function allowedPreferredChannelIds(int $tenantId): array
    {
        $state = $this->resolvedState($tenantId);
        $ids = [ContactChannelType::Phone->value];

        foreach (ContactChannelType::preferredSelectable() as $type) {
            $key = $type->value;
            if ($key === ContactChannelType::Phone->value) {
                continue;
            }
            $cfg = $state[$key] ?? null;
            if ($cfg === null) {
                continue;
            }
            if ($cfg->usesChannel && $cfg->allowedInForms) {
                $ids[] = $key;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array{id: string, label: string, needs_value: bool, needs_phone: bool, value_hint: string, value_placeholder: string, value_label: string}>
     */
    public function publicFormPreferredOptions(int $tenantId): array
    {
        $localeRaw = strtolower(trim((string) Tenant::query()->whereKey($tenantId)->value('locale')));
        $enForms = str_starts_with($localeRaw, 'en');

        $allowed = $this->allowedPreferredChannelIds($tenantId);
        $options = [];
        foreach ($allowed as $id) {
            $needs = ContactChannelRegistry::requiresVisitorValue($id);
            $needsPhone = in_array($id, [
                ContactChannelType::Phone->value,
                ContactChannelType::Whatsapp->value,
            ], true);

            $label = ($id === ContactChannelType::Phone->value && $enForms)
                ? 'Phone'
                : (($id === ContactChannelType::Phone->value && ! $enForms)
                    ? 'Только телефон'
                    : ($enForms
                        ? 'Prefer '.ContactChannelRegistry::labelEnPublic($id)
                        : 'Предпочитаю: '.ContactChannelRegistry::label($id)));

            $options[] = [
                'id' => $id,
                'label' => $label,
                'needs_value' => $needs,
                'needs_phone' => $needsPhone,
                'value_hint' => $needs
                    ? ($enForms ? ContactChannelRegistry::visitorValueHintEn($id) : ContactChannelRegistry::visitorValueHintRu($id))
                    : '',
                'value_placeholder' => $needs
                    ? ($enForms ? ContactChannelRegistry::visitorValuePlaceholderEn($id) : ContactChannelRegistry::visitorValuePlaceholderRu($id))
                    : '',
                'value_label' => $needs
                    ? ($enForms ? ContactChannelRegistry::visitorValueFieldLabelEn($id) : ContactChannelRegistry::visitorValueFieldLabelRu($id))
                    : '',
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, TenantContactChannelConfig>  $out
     * @return array<string, TenantContactChannelConfig>
     */
    private function applyBootstrapFromLegacyContacts(int $tenantId, array $out): array
    {
        $phone = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.phone', ''));
        $wa = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.whatsapp', ''));
        $tg = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.telegram', ''));

        if ($phone !== '') {
            $c = $out[ContactChannelType::Phone->value];
            $out[ContactChannelType::Phone->value] = new TenantContactChannelConfig(
                usesChannel: true,
                publicVisible: $c->publicVisible,
                allowedInForms: $c->allowedInForms,
                businessValue: $c->businessValue !== '' ? $c->businessValue : $phone,
                sortOrder: $c->sortOrder,
            );
        }

        if ($wa !== '' || $phone !== '') {
            $c = $out[ContactChannelType::Whatsapp->value];
            $out[ContactChannelType::Whatsapp->value] = new TenantContactChannelConfig(
                usesChannel: true,
                publicVisible: $c->publicVisible,
                allowedInForms: true,
                businessValue: $c->businessValue !== '' ? $c->businessValue : ($wa !== '' ? $wa : $phone),
                sortOrder: $c->sortOrder,
            );
        }

        if ($tg !== '') {
            $c = $out[ContactChannelType::Telegram->value];
            $out[ContactChannelType::Telegram->value] = new TenantContactChannelConfig(
                usesChannel: true,
                publicVisible: $c->publicVisible,
                allowedInForms: true,
                businessValue: $c->businessValue !== '' ? $c->businessValue : $tg,
                sortOrder: $c->sortOrder,
            );
        }

        $vk = trim((string) TenantSetting::getForTenant($tenantId, 'contacts.vk_url', ''));
        if ($vk !== '') {
            $c = $out[ContactChannelType::Vk->value];
            $out[ContactChannelType::Vk->value] = new TenantContactChannelConfig(
                usesChannel: true,
                publicVisible: $c->publicVisible,
                allowedInForms: true,
                businessValue: $c->businessValue !== '' ? $c->businessValue : $vk,
                sortOrder: $c->sortOrder,
            );
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultRowForType(ContactChannelType $type): array
    {
        return [
            'uses_channel' => false,
            'public_visible' => false,
            'allowed_in_forms' => false,
            'business_value' => '',
            'sort_order' => ContactChannelRegistry::defaultSort($type->value),
        ];
    }
}
