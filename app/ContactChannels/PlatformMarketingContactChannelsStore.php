<?php

namespace App\ContactChannels;

use App\Models\PlatformSetting;

/**
 * Каналы для публичной формы маркетингового сайта (PlatformSetting), структура строк как у тенанта.
 */
class PlatformMarketingContactChannelsStore
{
    public const SETTING_KEY = 'marketing.contact_channels.state';

    public const PREFERRED_EMAIL = 'email';

    /**
     * @return array<string, TenantContactChannelConfig>
     */
    public function resolvedState(): array
    {
        $hasSavedState = PlatformSetting::query()
            ->where('key', self::SETTING_KEY)
            ->exists();

        $stored = PlatformSetting::get(self::SETTING_KEY, null);
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
            $p = $out[ContactChannelType::Phone->value];
            $out[ContactChannelType::Phone->value] = new TenantContactChannelConfig(
                usesChannel: true,
                publicVisible: $p->publicVisible,
                allowedInForms: true,
                businessValue: $p->businessValue,
                sortOrder: $p->sortOrder,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rawMap
     */
    public function persist(array $rawMap): void
    {
        $clean = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $key = $type->value;
            $row = $rawMap[$key] ?? [];
            $defaults = $this->defaultRowForType($type);
            $merged = array_merge($defaults, is_array($row) ? $row : []);
            $clean[$key] = TenantContactChannelConfig::fromArray($merged)->toArray();
        }

        PlatformSetting::set(self::SETTING_KEY, $clean, 'json');
    }

    /**
     * @return list<string>
     */
    public function allowedPreferredChannelIds(): array
    {
        $state = $this->resolvedState();
        $ids = [self::PREFERRED_EMAIL];

        foreach (ContactChannelType::preferredSelectable() as $type) {
            $key = $type->value;
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
     * @return list<array{id: string, label: string, needs_value: bool, needs_phone: bool, value_hint: string, value_placeholder: string}>
     */
    public function publicFormPreferredOptions(): array
    {
        $allowed = $this->allowedPreferredChannelIds();
        $options = [];
        foreach ($allowed as $id) {
            if ($id === self::PREFERRED_EMAIL) {
                $options[] = [
                    'id' => self::PREFERRED_EMAIL,
                    'label' => 'Связь по email (указанному выше)',
                    'needs_value' => false,
                    'needs_phone' => false,
                    'value_hint' => '',
                    'value_placeholder' => '',
                ];

                continue;
            }

            $needs = ContactChannelRegistry::requiresVisitorValue($id);
            $needsPhone = in_array($id, [
                ContactChannelType::Phone->value,
                ContactChannelType::Whatsapp->value,
            ], true);
            $options[] = [
                'id' => $id,
                'label' => match ($id) {
                    ContactChannelType::Phone->value => 'Звонок или SMS на телефон',
                    default => 'Предпочитаю: '.ContactChannelRegistry::label($id),
                },
                'needs_value' => $needs,
                'needs_phone' => $needsPhone,
                'value_hint' => $needs ? ContactChannelRegistry::visitorValueHintRu($id) : '',
                'value_placeholder' => $needs ? ContactChannelRegistry::visitorValuePlaceholderRu($id) : '',
            ];
        }

        return $options;
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
