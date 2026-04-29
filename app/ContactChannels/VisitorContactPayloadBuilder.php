<?php

namespace App\ContactChannels;

use App\Models\Tenant;
use App\Support\Phone\IntlPhoneNormalizer;
use Illuminate\Validation\ValidationException;

/**
 * Единый билдер: phone в JSON всегда + preferred_* синхронно с массивом.
 */
final class VisitorContactPayloadBuilder
{
    public function __construct(
        private readonly TenantContactChannelsStore $tenantChannels,
    ) {}

    /**
     * @param  array{phone?: string|null, preferred_contact_channel: string, preferred_contact_value?: ?string}  $input
     * @return array{preferred_contact_channel: string, preferred_contact_value: ?string, visitor_contact_channels_json: list<array<string, mixed>>}
     */
    public function build(int $tenantId, array $input): array
    {
        $englishUi = Tenant::query()->where('id', $tenantId)->value('theme_key') === 'expert_pr';

        $phone = IntlPhoneNormalizer::normalizePhone((string) ($input['phone'] ?? ''));
        if ($phone === '' || ! IntlPhoneNormalizer::validatePhone($phone)) {
            throw ValidationException::withMessages([
                'phone' => $englishUi
                    ? 'Enter a valid phone number (E.164), or leave phone empty when using email.'
                    : 'Укажите корректный телефон в международном формате.',
            ]);
        }

        $preferred = (string) ($input['preferred_contact_channel'] ?? ContactChannelType::Phone->value);
        $allowed = $this->tenantChannels->allowedPreferredChannelIds($tenantId);
        if ($englishUi) {
            $allowed = array_values(array_unique([...$allowed, ContactChannelType::Email->value]));
        }
        if (! in_array($preferred, $allowed, true)) {
            throw ValidationException::withMessages([
                'preferred_contact_channel' => $englishUi
                    ? 'Selected contact channel is not available.'
                    : 'Выбран недопустимый способ связи.',
            ]);
        }

        $extraRaw = isset($input['preferred_contact_value']) ? trim((string) $input['preferred_contact_value']) : '';

        if (ContactChannelRegistry::requiresVisitorValue($preferred)) {
            if ($extraRaw === '') {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => PreferredContactValueMessages::required($preferred, $englishUi),
                ]);
            }
        }

        $channels = [];

        $channels[] = [
            'type' => ContactChannelType::Phone->value,
            'value' => $phone,
        ];

        $preferredValue = null;

        if ($preferred === ContactChannelType::Phone->value) {
            $preferredValue = $phone;
        } elseif ($preferred === ContactChannelType::Whatsapp->value) {
            $preferredValue = $phone;
            $channels[] = [
                'type' => ContactChannelType::Whatsapp->value,
                'value' => $phone,
                'meta' => ['uses_same_phone' => true],
            ];
        } elseif ($preferred === ContactChannelType::Telegram->value) {
            $u = VisitorContactNormalizer::normalizeTelegram($extraRaw);
            if ($u === null) {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => PreferredContactValueMessages::invalidFormat($preferred, $englishUi),
                ]);
            }
            $preferredValue = $u;
            $row = [
                'type' => ContactChannelType::Telegram->value,
                'value' => $u,
            ];
            if ($extraRaw !== '' && $extraRaw !== $u && '@'.$u !== $extraRaw && 't.me/'.$u !== strtolower($extraRaw)) {
                $row['raw_value'] = $extraRaw;
            }
            $channels[] = $row;
        } elseif ($preferred === ContactChannelType::Vk->value) {
            $url = VisitorContactNormalizer::normalizeVk($extraRaw);
            if ($url === null) {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => PreferredContactValueMessages::invalidFormat($preferred, $englishUi),
                ]);
            }
            $preferredValue = $url;
            $row = ['type' => ContactChannelType::Vk->value, 'value' => $url];
            if ($extraRaw !== $url) {
                $row['raw_value'] = $extraRaw;
            }
            $channels[] = $row;
        } elseif ($preferred === ContactChannelType::Max->value) {
            $v = VisitorContactNormalizer::normalizeMax($extraRaw);
            if ($v === null) {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => PreferredContactValueMessages::invalidFormat($preferred, $englishUi),
                ]);
            }
            $preferredValue = $v;
            $row = ['type' => ContactChannelType::Max->value, 'value' => $v];
            if ($extraRaw !== $v) {
                $row['raw_value'] = $extraRaw;
            }
            $channels[] = $row;
        } elseif ($preferred === ContactChannelType::Email->value) {
            $e = strtolower(trim($extraRaw));
            if ($e === '' || filter_var($e, FILTER_VALIDATE_EMAIL) === false) {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => PreferredContactValueMessages::invalidFormat($preferred, $englishUi),
                ]);
            }
            $preferredValue = $e;
            $channels[] = [
                'type' => ContactChannelType::Email->value,
                'value' => $e,
            ];
        }

        if ($preferredValue === null) {
            $preferredValue = $phone;
        }

        foreach ($channels as $i => $ch) {
            if (isset($ch['raw_value']) && $ch['raw_value'] === '') {
                unset($channels[$i]['raw_value']);
            }
        }

        return [
            'preferred_contact_channel' => $preferred,
            'preferred_contact_value' => $preferredValue,
            'visitor_contact_channels_json' => array_values($channels),
        ];
    }

    /**
     * Expert PR/public: reply path when the visitor leaves email without a phone (CRM still gets a valid contact row).
     *
     * @return array{preferred_contact_channel: string, preferred_contact_value: string, visitor_contact_channels_json: list<array<string, mixed>>}
     */
    public function buildEmailOnlyPreferred(string $email): array
    {
        $e = strtolower(trim($email));
        if ($e === '' || filter_var($e, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'contact_email' => 'Enter a valid email address.',
            ]);
        }

        return [
            'preferred_contact_channel' => ContactChannelType::Email->value,
            'preferred_contact_value' => $e,
            'visitor_contact_channels_json' => [
                [
                    'type' => ContactChannelType::Email->value,
                    'value' => $e,
                ],
            ],
        ];
    }
}
