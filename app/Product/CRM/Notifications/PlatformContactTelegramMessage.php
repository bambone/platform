<?php

namespace App\Product\CRM\Notifications;

use App\Models\CrmRequest;

/**
 * Plain-text body for platform marketing contact alerts (no Markdown/HTML).
 */
final class PlatformContactTelegramMessage
{
    public static function build(CrmRequest $crm): string
    {
        $lines = ['Новая заявка с маркетингового сайта', ''];

        $lines[] = 'ID: '.$crm->id;
        $lines[] = 'Тип: '.$crm->request_type;

        $name = trim((string) $crm->name);
        if ($name !== '') {
            $lines[] = 'Имя: '.$name;
        }

        $phone = trim((string) $crm->phone);
        if ($phone !== '') {
            $lines[] = 'Телефон: '.$phone;
        }

        $email = trim((string) ($crm->email ?? ''));
        if ($email !== '') {
            $lines[] = 'Email: '.$email;
        }

        $pref = trim((string) ($crm->preferred_contact_channel ?? ''));
        if ($pref !== '') {
            $lines[] = 'Предпочтительный канал: '.$pref;
        }

        $prefVal = trim((string) ($crm->preferred_contact_value ?? ''));
        if ($prefVal !== '') {
            $lines[] = 'Контакт (канал): '.$prefVal;
        }

        $payload = is_array($crm->payload_json) ? $crm->payload_json : [];
        $intent = isset($payload['intent']) ? trim((string) $payload['intent']) : '';
        $intentLabel = isset($payload['intent_label']) ? trim((string) $payload['intent_label']) : '';
        if ($intent !== '' || $intentLabel !== '') {
            $lines[] = '';
            if ($intent !== '') {
                $lines[] = 'Intent: '.$intent;
            }
            if ($intentLabel !== '' && $intentLabel !== $intent) {
                $lines[] = 'Intent (подпись): '.$intentLabel;
            }
        }

        $message = trim((string) ($crm->message ?? ''));
        if ($message !== '') {
            $preview = mb_strlen($message) > 1200 ? mb_substr($message, 0, 1197).'…' : $message;
            $lines[] = '';
            $lines[] = 'Сообщение:';
            $lines[] = $preview;
        }

        $utmLines = self::utmSectionLines($crm);
        if ($utmLines !== []) {
            $lines[] = '';
            $lines = array_merge($lines, $utmLines);
        }

        $text = implode("\n", $lines);

        return mb_substr($text, 0, 4096);
    }

    /**
     * @return list<string>
     */
    private static function utmSectionLines(CrmRequest $crm): array
    {
        $pairs = [
            'UTM Source' => trim((string) ($crm->utm_source ?? '')),
            'UTM Medium' => trim((string) ($crm->utm_medium ?? '')),
            'UTM Campaign' => trim((string) ($crm->utm_campaign ?? '')),
            'UTM Content' => trim((string) ($crm->utm_content ?? '')),
            'UTM Term' => trim((string) ($crm->utm_term ?? '')),
        ];

        $nonEmpty = array_filter($pairs, static fn (string $v): bool => $v !== '');
        if ($nonEmpty === []) {
            return [];
        }

        $out = ['UTM:'];
        foreach ($nonEmpty as $label => $value) {
            $out[] = $label.': '.$value;
        }

        return $out;
    }
}
