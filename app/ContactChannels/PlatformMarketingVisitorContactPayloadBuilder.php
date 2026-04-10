<?php

namespace App\ContactChannels;

use App\Support\Phone\IntlPhoneNormalizer;
use Illuminate\Validation\ValidationException;

/**
 * Сбор preferred + visitor_contact_channels_json для формы маркетинга: email обязателен и всегда в JSON.
 */
final class PlatformMarketingVisitorContactPayloadBuilder
{
    public function __construct(
        private readonly PlatformMarketingContactChannelsStore $channels,
    ) {}

    /**
     * @param  array{email: string, phone?: ?string, preferred_contact_channel?: string, preferred_contact_value?: ?string}  $input
     * @return array{phone: ?string, preferred_contact_channel: string, preferred_contact_value: ?string, visitor_contact_channels_json: list<array<string, mixed>>}
     */
    public function build(array $input): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'Укажите email.',
            ]);
        }

        $preferred = (string) ($input['preferred_contact_channel'] ?? PlatformMarketingContactChannelsStore::PREFERRED_EMAIL);
        $allowed = $this->channels->allowedPreferredChannelIds();
        if (! in_array($preferred, $allowed, true)) {
            throw ValidationException::withMessages([
                'preferred_contact_channel' => 'Выбран недопустимый способ связи.',
            ]);
        }

        $extraRaw = isset($input['preferred_contact_value']) ? trim((string) $input['preferred_contact_value']) : '';
        $phoneRaw = isset($input['phone']) ? trim((string) $input['phone']) : '';
        $normalizedPhone = $phoneRaw !== '' ? IntlPhoneNormalizer::normalizePhone($phoneRaw) : '';

        if (ContactChannelRegistry::requiresVisitorValue($preferred)) {
            if ($extraRaw === '') {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => 'Укажите контакт для выбранного способа связи.',
                ]);
            }
        }

        $channels = [
            [
                'type' => PlatformMarketingContactChannelsStore::PREFERRED_EMAIL,
                'value' => $email,
            ],
        ];

        if ($preferred === PlatformMarketingContactChannelsStore::PREFERRED_EMAIL) {
            return [
                'phone' => null,
                'preferred_contact_channel' => PlatformMarketingContactChannelsStore::PREFERRED_EMAIL,
                'preferred_contact_value' => $email,
                'visitor_contact_channels_json' => $channels,
            ];
        }

        if ($preferred === ContactChannelType::Phone->value) {
            if ($normalizedPhone === '' || ! IntlPhoneNormalizer::validatePhone($normalizedPhone)) {
                throw ValidationException::withMessages([
                    'phone' => 'Укажите корректный телефон в международном формате.',
                ]);
            }
            $channels[] = [
                'type' => ContactChannelType::Phone->value,
                'value' => $normalizedPhone,
            ];

            return [
                'phone' => $normalizedPhone,
                'preferred_contact_channel' => $preferred,
                'preferred_contact_value' => $normalizedPhone,
                'visitor_contact_channels_json' => array_values($channels),
            ];
        }

        if ($preferred === ContactChannelType::Whatsapp->value) {
            if ($normalizedPhone === '' || ! IntlPhoneNormalizer::validatePhone($normalizedPhone)) {
                throw ValidationException::withMessages([
                    'phone' => 'Укажите корректный телефон для WhatsApp.',
                ]);
            }
            $channels[] = [
                'type' => ContactChannelType::Phone->value,
                'value' => $normalizedPhone,
            ];
            $channels[] = [
                'type' => ContactChannelType::Whatsapp->value,
                'value' => $normalizedPhone,
                'meta' => ['uses_same_phone' => true],
            ];

            return [
                'phone' => $normalizedPhone,
                'preferred_contact_channel' => $preferred,
                'preferred_contact_value' => $normalizedPhone,
                'visitor_contact_channels_json' => array_values($channels),
            ];
        }

        if ($preferred === ContactChannelType::Telegram->value) {
            $u = VisitorContactNormalizer::normalizeTelegram($extraRaw);
            if ($u === null) {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => 'Укажите корректный Telegram (username или ссылка t.me/…).',
                ]);
            }
            $row = [
                'type' => ContactChannelType::Telegram->value,
                'value' => $u,
            ];
            if ($extraRaw !== '' && $extraRaw !== $u && '@'.$u !== $extraRaw && 't.me/'.$u !== strtolower($extraRaw)) {
                $row['raw_value'] = $extraRaw;
            }
            $channels[] = $row;

            return [
                'phone' => $normalizedPhone !== '' && IntlPhoneNormalizer::validatePhone($normalizedPhone) ? $normalizedPhone : null,
                'preferred_contact_channel' => $preferred,
                'preferred_contact_value' => $u,
                'visitor_contact_channels_json' => array_values($channels),
            ];
        }

        if ($preferred === ContactChannelType::Vk->value) {
            $url = VisitorContactNormalizer::normalizeVk($extraRaw);
            if ($url === null) {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => 'Укажите ссылку на профиль VK или id/slug.',
                ]);
            }
            $row = ['type' => ContactChannelType::Vk->value, 'value' => $url];
            if ($extraRaw !== $url) {
                $row['raw_value'] = $extraRaw;
            }
            $channels[] = $row;

            return [
                'phone' => $normalizedPhone !== '' && IntlPhoneNormalizer::validatePhone($normalizedPhone) ? $normalizedPhone : null,
                'preferred_contact_channel' => $preferred,
                'preferred_contact_value' => $url,
                'visitor_contact_channels_json' => array_values($channels),
            ];
        }

        if ($preferred === ContactChannelType::Max->value) {
            $v = VisitorContactNormalizer::normalizeMax($extraRaw);
            if ($v === null) {
                throw ValidationException::withMessages([
                    'preferred_contact_value' => 'Укажите контакт MAX (текст или ссылку).',
                ]);
            }
            $row = ['type' => ContactChannelType::Max->value, 'value' => $v];
            if ($extraRaw !== $v) {
                $row['raw_value'] = $extraRaw;
            }
            $channels[] = $row;

            return [
                'phone' => $normalizedPhone !== '' && IntlPhoneNormalizer::validatePhone($normalizedPhone) ? $normalizedPhone : null,
                'preferred_contact_channel' => $preferred,
                'preferred_contact_value' => $v,
                'visitor_contact_channels_json' => array_values($channels),
            ];
        }

        throw ValidationException::withMessages([
            'preferred_contact_channel' => 'Выбран недопустимый способ связи.',
        ]);
    }
}
