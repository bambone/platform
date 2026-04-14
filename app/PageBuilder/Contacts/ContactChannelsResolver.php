<?php

namespace App\PageBuilder\Contacts;

use App\Support\RussianPhone;

/**
 * Normalizes channels, builds hrefs, splits primary/secondary (usable only), legacy synthesis.
 */
final class ContactChannelsResolver
{
    public function __construct(
        private readonly ContactChannelRegistry $registry,
    ) {}

    public function present(array $data): ContactSectionPresentation
    {
        $title = trim((string) ($data['title'] ?? $data['heading'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $workingHours = trim((string) ($data['working_hours'] ?? ''));
        $additionalNote = trim((string) ($data['additional_note'] ?? ''));

        $mapBlock = app(ContactMapPublicResolver::class)->resolve($data);

        $rows = $this->effectiveChannelRows($data);
        $primary = [];
        $secondary = [];
        foreach ($rows as $row) {
            $resolved = $this->resolveRow($row, forPublic: true);
            if ($resolved === null) {
                continue;
            }
            if ($this->rowIsPrimary($row)) {
                $primary[] = $resolved;
            } else {
                $secondary[] = $resolved;
            }
        }

        return new ContactSectionPresentation(
            title: $title,
            description: $description,
            address: $address,
            workingHours: $workingHours,
            mapBlock: $mapBlock,
            additionalNote: $additionalNote,
            primaryChannels: $primary,
            secondaryChannels: $secondary,
        );
    }

    public function analyze(array $data): ContactsInfoAdminAnalysis
    {
        $address = trim((string) ($data['address'] ?? ''));
        $hours = trim((string) ($data['working_hours'] ?? ''));
        $hasMap = ContactMapCanonical::fromDataJson($data)->hasVisibleMap();

        $rawRows = $this->normalizeStoredChannels($data['channels'] ?? []);
        $legacyRows = $this->legacySyntheticRows($data);
        // Админка: если в форме уже есть строки каналов — анализируем их (в т.ч. битые), иначе legacy.
        $rows = $rawRows !== [] ? $rawRows : $legacyRows;

        $enabled = 0;
        $usable = 0;
        $broken = 0;
        $usablePrimary = 0;
        /** @var array<int, list<string>> $rowIssues */
        $rowIssues = [];
        $warnings = [];

        foreach ($rows as $i => $row) {
            if ($this->rowIsEnabled($row)) {
                $enabled++;
            }
            $issues = $this->collectRowIssues($row, $data);
            if ($issues !== []) {
                $rowIssues[$i] = $issues;
            }
            $resolved = $this->resolveRow($row, forPublic: true);
            if ($resolved !== null) {
                $usable++;
                if ($this->rowIsPrimary($row)) {
                    $usablePrimary++;
                }
            } elseif ($this->rowIsEnabled($row)) {
                $broken++;
            }
        }

        if ($enabled > 0 && $usable === 0) {
            $warnings[] = 'Ни один включённый канал не выводится на сайте — проверьте значения и ссылки.';
        } elseif ($broken > 0) {
            $warnings[] = $broken.' '.self::pluralChannels($broken).' включено, но не отображается (ошибки в строках).';
        }

        if ($usable > 0 && $usablePrimary === 0) {
            $warnings[] = 'Нет основного канала среди активных — на сайте всё пойдёт во второй блок.';
        }

        if ($usable === 0 && $address === '') {
            $warnings[] = 'Нет активных каналов и пустой адрес.';
        }

        if (! $hasMap && $address === '' && $usable === 0) {
            $warnings[] = 'Нет карты, адреса и активных каналов.';
        }

        return new ContactsInfoAdminAnalysis(
            enabledCount: $enabled,
            usableCount: $usable,
            brokenEnabledCount: $broken,
            warnings: $warnings,
            rowIssues: $rowIssues,
            usablePrimaryCount: $usablePrimary,
            hasAddress: $address !== '',
            hasHours: $hours !== '',
            hasMap: $hasMap,
        );
    }

    /**
     * Preview href for admin (does not persist).
     */
    public function previewHrefForRow(array $row): ?string
    {
        $r = $this->resolveRow($row, forPublic: false);

        return $r?->href;
    }

    /**
     * Whether stored channels have at least one usable (legacy not counted).
     */
    public function hasUsableStoredChannels(array $data): bool
    {
        $raw = $this->normalizeStoredChannels($data['channels'] ?? []);

        return $this->hasAnyUsableAmongRows($raw, $data);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function hasAnyUsableAmongRows(array $rows, array $fullData): bool
    {
        foreach ($rows as $row) {
            if ($this->resolveRow($row, forPublic: true) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rows for public: either normalized channels if any usable, else legacy synthesis.
     *
     * @return list<array<string, mixed>>
     */
    private function effectiveChannelRows(array $data): array
    {
        $raw = $this->normalizeStoredChannels($data['channels'] ?? []);
        if ($raw !== [] && $this->hasAnyUsableAmongRows($raw, $data)) {
            return $raw;
        }

        return $this->legacySyntheticRows($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legacySyntheticRows(array $data): array
    {
        $out = [];
        $order = 0;
        foreach (['phone' => ContactChannelType::Phone, 'email' => ContactChannelType::Email, 'whatsapp' => ContactChannelType::Whatsapp, 'telegram' => ContactChannelType::Telegram] as $key => $type) {
            $v = trim((string) ($data[$key] ?? ''));
            if ($v === '') {
                continue;
            }
            $out[] = [
                'type' => $type->value,
                'label' => null,
                'value' => $v,
                'url' => null,
                'is_override_url' => false,
                'is_enabled' => true,
                'is_primary' => in_array($key, ['phone', 'whatsapp', 'telegram'], true),
                'sort_order' => $order++,
                'note' => null,
                'cta_label' => null,
                'open_in_new_tab' => null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeStoredChannels(mixed $channels): array
    {
        if (! is_array($channels)) {
            return [];
        }
        $rows = [];
        foreach ($channels as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $row['_index'] = $i;
            $rows[] = $row;
        }
        usort($rows, function (array $a, array $b): int {
            $sa = (int) ($a['sort_order'] ?? 0);
            $sb = (int) ($b['sort_order'] ?? 0);
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return ((int) ($a['_index'] ?? 0)) <=> ((int) ($b['_index'] ?? 0));
        });

        return $rows;
    }

    private function rowIsEnabled(array $row): bool
    {
        $v = $row['is_enabled'] ?? true;
        if (is_bool($v)) {
            return $v;
        }
        if ($v === 0 || $v === '0' || $v === 'false' || $v === false) {
            return false;
        }

        return true;
    }

    private function rowIsPrimary(array $row): bool
    {
        $v = $row['is_primary'] ?? false;
        if (is_bool($v)) {
            return $v;
        }
        if ($v === 1 || $v === '1' || $v === 'true') {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function collectRowIssues(array $row, array $data): array
    {
        $issues = [];
        if (! $this->rowIsEnabled($row)) {
            return [];
        }
        $type = ContactChannelType::tryFromMixed($row['type'] ?? '');
        if ($type === null) {
            $issues[] = 'Неизвестный тип канала.';

            return $issues;
        }
        $value = trim((string) ($row['value'] ?? ''));
        if ($value === '') {
            $issues[] = 'Заполните значение.';

            return $issues;
        }
        if ($type === ContactChannelType::GenericUrl) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                $issues[] = 'Для «Ссылка / другой канал» нужна подпись на сайте.';
            }
        }
        if ($type === ContactChannelType::SiteForm) {
            $issues = array_merge($issues, $this->siteFormHeuristicWarnings($value));
        }
        $resolved = $this->resolveRow($row, forPublic: true);
        if ($resolved === null) {
            $issues[] = 'Не удаётся собрать ссылку — проверьте формат.';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return list<string>
     */
    private function siteFormHeuristicWarnings(string $value): array
    {
        $v = trim($value);
        $out = [];
        if ($v === '') {
            return $out;
        }
        if (preg_match('#^https?://#i', $v) === 1) {
            $out[] = 'Похоже на внешний URL. Для формы на сайте обычно используют якорь (#…) или путь (/…).';
        }
        if (str_starts_with($v, '#') && strlen($v) < 2) {
            $out[] = 'Пустой якорь. Укажите id формы, например #lead-form.';
        }

        return $out;
    }

    private function resolveRow(array $row, bool $forPublic): ?ResolvedContactChannel
    {
        if (! $this->rowIsEnabled($row)) {
            return null;
        }
        $type = ContactChannelType::tryFromMixed($row['type'] ?? '');
        if ($type === null) {
            return null;
        }
        $value = trim((string) ($row['value'] ?? ''));
        if ($value === '') {
            return null;
        }

        $override = trim((string) ($row['url'] ?? ''));
        $useOverride = $override !== '' && $this->truthy($row['is_override_url'] ?? false);

        $href = $useOverride ? $this->normalizeOverrideHref($override) : $this->buildHrefFromValue($type, $value);
        if ($href === null || $href === '') {
            return null;
        }

        $customLabel = trim((string) ($row['label'] ?? ''));

        $registry = $this->registry;
        $cta = trim((string) ($row['cta_label'] ?? ''));
        if ($cta === '') {
            $cta = $registry->defaultCtaLabel($type);
        }

        if ($type === ContactChannelType::GenericUrl) {
            $display = $customLabel !== '' ? $customLabel : 'Ссылка';
        } else {
            $display = $customLabel !== '' ? $customLabel : ($this->defaultDisplayValue($type, $value) ?? $value);
        }

        $openRaw = $row['open_in_new_tab'] ?? null;
        if ($openRaw === null || $openRaw === '' || $openRaw === 'inherit') {
            $open = $registry->defaultOpenInNewTab($type);
        } else {
            $open = filter_var($openRaw, FILTER_VALIDATE_BOOLEAN);
        }

        $rel = $open ? 'noopener noreferrer' : null;
        $note = trim((string) ($row['note'] ?? ''));
        if ($note === '') {
            $note = null;
        }

        return new ResolvedContactChannel(
            type: $type,
            href: $href,
            displayValue: $display,
            ctaLabel: $cta,
            openInNewTab: $open,
            rel: $rel,
            icon: $registry->icon($type),
            note: $note,
        );
    }

    private function normalizeOverrideHref(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        if (str_starts_with($t, '#') || str_starts_with($t, '/')) {
            return $t;
        }
        if (str_starts_with($t, 'tel:') || str_starts_with($t, 'mailto:')) {
            return $t;
        }
        $http = $this->permissiveHttpUrl($t);
        if ($http !== null) {
            return $http;
        }
        if (filter_var($t, FILTER_VALIDATE_URL)) {
            return $t;
        }

        return null;
    }

    /**
     * http(s) с непустым host — шире, чем FILTER_VALIDATE_URL (кириллические домены, часть query/fragment).
     */
    private function permissiveHttpUrl(string $v): ?string
    {
        $v = trim($v);
        if ($v === '' || preg_match('#^https?://#i', $v) !== 1) {
            return null;
        }
        $host = parse_url($v, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $v : null;
    }

    private function buildHrefFromValue(ContactChannelType $type, string $value): ?string
    {
        $v = trim($value);

        return match ($type) {
            ContactChannelType::Phone => $this->hrefPhone($v),
            ContactChannelType::Email => $this->hrefEmail($v),
            ContactChannelType::Telegram => $this->hrefTelegram($v),
            ContactChannelType::Vk => $this->hrefVk($v),
            ContactChannelType::Whatsapp => $this->hrefWhatsapp($v),
            ContactChannelType::Viber => $this->hrefViber($v),
            ContactChannelType::Instagram => $this->hrefInstagram($v),
            ContactChannelType::FacebookMessenger => $this->hrefMessenger($v),
            ContactChannelType::Sms => $this->hrefSms($v),
            ContactChannelType::Max => $this->hrefMax($v),
            ContactChannelType::GenericUrl => $this->permissiveHttpUrl($v) ?? (filter_var($v, FILTER_VALIDATE_URL) ? $v : null),
            ContactChannelType::SiteForm => $this->hrefSiteForm($v),
        };
    }

    private function hrefPhone(string $v): ?string
    {
        $norm = RussianPhone::normalize($v);
        if ($norm !== null) {
            $digits = preg_replace('/\D+/', '', $norm) ?? '';

            return $digits !== '' ? 'tel:'.$digits : null;
        }
        $digits = preg_replace('/\D+/', '', $v) ?? '';

        return strlen($digits) >= 10 ? 'tel:'.$digits : null;
    }

    private function hrefEmail(string $v): ?string
    {
        $e = filter_var($v, FILTER_VALIDATE_EMAIL);

        return $e ? 'mailto:'.$e : null;
    }

    private function hrefTelegram(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('#^(?:https?://)?(t\.me|telegram\.me)/#i', $v) === 1) {
            if (preg_match('#^https?://#i', $v) !== 1) {
                $v = 'https://'.$v;
            }

            return $this->permissiveHttpUrl($v);
        }
        $u = ltrim(rtrim($v, '/'), '@');
        if ($u === '') {
            return null;
        }
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{3,31}$/', $u) === 1) {
            return 'https://t.me/'.$u;
        }

        return $this->permissiveHttpUrl($v);
    }

    private function hrefVk(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('#^(?:https?://)?(?:www\.)?vk\.com/.+#i', $v) === 1) {
            if (preg_match('#^https?://#i', $v) !== 1) {
                $v = 'https://'.$v;
            }

            return $this->permissiveHttpUrl($v);
        }
        if (preg_match('#^https?://#i', $v) === 1) {
            return $this->permissiveHttpUrl($v);
        }
        $id = preg_replace('#^/+#', '', $v);
        if ($id === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $id) !== 1) {
            return null;
        }

        return 'https://vk.com/'.$id;
    }

    private function hrefWhatsapp(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('#^(?:https?://)?wa\.me/#i', $v) === 1) {
            if (preg_match('#^https?://#i', $v) !== 1) {
                $v = 'https://'.$v;
            }

            return $this->permissiveHttpUrl($v);
        }
        $digits = preg_replace('/\D+/', '', $v) ?? '';

        return strlen($digits) >= 10 ? 'https://wa.me/'.$digits : null;
    }

    private function hrefViber(string $v): ?string
    {
        $v = trim($v);
        if (str_starts_with(strtolower($v), 'viber:')) {
            return $v;
        }
        if (preg_match('#^https?://#i', $v) === 1) {
            return $this->permissiveHttpUrl($v);
        }
        $digits = preg_replace('/\D+/', '', $v) ?? '';
        if (strlen($digits) < 10) {
            return null;
        }
        $n = str_starts_with($digits, '0') ? $digits : $digits;

        return 'viber://chat?number=%2B'.$n;
    }

    private function hrefInstagram(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('#^(?:https?://)?(?:www\.)?instagram\.com/.+#i', $v) === 1) {
            if (preg_match('#^https?://#i', $v) !== 1) {
                $v = 'https://'.$v;
            }

            return $this->permissiveHttpUrl($v);
        }
        if (preg_match('#^https?://(www\.)?instagram\.com/#i', $v) === 1) {
            return $this->permissiveHttpUrl($v);
        }
        $u = ltrim(rtrim($v, '/'), '@');
        if ($u === '' || preg_match('/^[a-zA-Z0-9._]+$/', $u) !== 1) {
            return null;
        }

        return 'https://instagram.com/'.$u;
    }

    private function hrefMessenger(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('#^(?:https?://)?m\.me/.+#i', $v) === 1) {
            if (preg_match('#^https?://#i', $v) !== 1) {
                $v = 'https://'.$v;
            }

            return $this->permissiveHttpUrl($v);
        }
        if (preg_match('#^https?://#i', $v) === 1) {
            return $this->permissiveHttpUrl($v);
        }
        $u = $v;
        if ($u === '') {
            return null;
        }

        return 'https://m.me/'.$u;
    }

    private function hrefSms(string $v): ?string
    {
        $digits = preg_replace('/\D+/', '', $v) ?? '';

        return strlen($digits) >= 10 ? 'sms:'.$digits : null;
    }

    private function hrefMax(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $v) === 1) {
            $host = parse_url($v, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? $v : null;
        }
        if (filter_var($v, FILTER_VALIDATE_URL)) {
            return $v;
        }
        // Без схемы: max.ru/… (часто копируют из приложения)
        if (preg_match('#^(?:www\.)?max\.ru/.+#i', $v) === 1) {
            $path = preg_replace('#^(?:https?://)?(?:www\.)?#i', '', $v);

            return 'https://'.$path;
        }
        // Только путь профиля u/…
        if (preg_match('#^u/[a-zA-Z0-9_-]{4,}/?$#i', $v) === 1) {
            return 'https://max.ru/'.trim($v, '/');
        }
        // Боты: @name → https://max.ru/@name
        if (preg_match('/^@[a-zA-Z0-9._-]{1,64}$/', $v) === 1) {
            return 'https://max.ru/'.$v;
        }
        // Ник на max.ru (не чистые цифры — иначе путаем с телефоном)
        $u = ltrim(rtrim($v, '/'), '@/');
        if ($u !== '' && preg_match('/^\d+$/', $u) !== 1 && preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $u) === 1) {
            return 'https://max.ru/'.$u;
        }

        return null;
    }

    private function hrefSiteForm(string $v): ?string
    {
        $t = trim($v);
        if ($t === '') {
            return null;
        }
        if (str_starts_with($t, '#')) {
            return $t;
        }
        if (str_starts_with($t, '/')) {
            return $t;
        }
        $ext = $this->permissiveHttpUrl($t);
        if ($ext !== null) {
            return $ext;
        }
        if (filter_var($t, FILTER_VALIDATE_URL)) {
            $host = parse_url($t, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $t;
            }
        }

        return null;
    }

    private function defaultDisplayValue(ContactChannelType $type, string $value): ?string
    {
        return match ($type) {
            ContactChannelType::Telegram => (static function (string $v): string {
                $u = ltrim(rtrim($v, '/'), '@');

                return str_starts_with($v, 'http') ? $v : '@'.$u;
            })($value),
            default => null,
        };
    }

    private function truthy(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if ($v === 1 || $v === '1' || $v === 'true') {
            return true;
        }

        return false;
    }

    private static function pluralChannels(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return 'каналов';
        }
        if ($n1 > 1 && $n1 < 5) {
            return 'канала';
        }
        if ($n1 === 1) {
            return 'канал';
        }

        return 'каналов';
    }
}
