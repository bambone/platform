<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

use App\Support\SafeMapPublicUrl;

/**
 * Parses combined map paste (https URL and/or iframe HTML). Raw HTML is never a public contract.
 */
final class ContactMapSourceParser
{
    public const COMBINED_INPUT_MAX_LENGTH = 12000;

    public const ERR_IFRAME_NO_SRC = 'Вставленный код не содержит iframe с допустимым src.';

    public const ERR_IFRAME_UNTRUSTED = 'Код карты относится к недоверенному домену и не может быть использован.';

    public const ERR_IFRAME_UNPARSEABLE = 'Код карты не удалось разобрать. Попробуйте вставить обычную ссылку на карту.';

    public const ERR_URL_ONLY_EXPECTED = 'Поддерживаются только обычные https-ссылки на Яндекс Карты, Google Maps и 2ГИС. HTML и iframe вставлять не нужно — вставьте ссылку в поле или включите режим «Авто».';

    public const ERR_PROVIDER_MISMATCH = 'Ссылка не относится к выбранному провайдеру. Выберите правильный сервис карты или вставьте ссылку с нужного домена.';

    public const LABEL_DETECTED_IFRAME = 'Определено: код карты';

    public const LABEL_DETECTED_URL = 'Определено: публичная ссылка';

    /**
     * Raw input: prefer `map_combined_input`, else `map_public_url` (legacy).
     *
     * @param  array<string, mixed>  $dataJson
     */
    public static function parseFromDataJson(array $dataJson): ContactMapParseResult
    {
        $mode = MapInputMode::fromDataJson($dataJson);
        $raw = self::rawCombinedInput($dataJson);
        $declared = MapProvider::tryFromMixed($dataJson['map_provider'] ?? '');

        return self::parse($mode, $raw, $declared);
    }

    /**
     * @param  array<string, mixed>  $dataJson
     */
    public static function rawCombinedInput(array $dataJson): string
    {
        if (array_key_exists('map_combined_input', $dataJson)) {
            return trim((string) $dataJson['map_combined_input']);
        }

        return trim((string) ($dataJson['map_public_url'] ?? ''));
    }

    /**
     * Дополнительная ссылка на другую карту (редактор: map_secondary_combined_input).
     *
     * @param  array<string, mixed>  $dataJson
     */
    public static function rawSecondaryCombinedInput(array $dataJson): string
    {
        if (array_key_exists('map_secondary_combined_input', $dataJson)) {
            return trim((string) $dataJson['map_secondary_combined_input']);
        }

        return trim((string) ($dataJson['map_secondary_public_url'] ?? ''));
    }

    /**
     * Если вставлен код iframe, а режим показа «только кнопка», переключаем на «карта и кнопка»,
     * иначе предпросмотр не покажет встраивание даже при валидном виджете.
     *
     * @param  array<string, mixed>  $dataJson
     */
    public static function maybeBumpDisplayModeForIframePaste(array $dataJson, ?string $rawCombinedInput): ?string
    {
        $raw = trim((string) $rawCombinedInput);
        if ($raw === '' || stripos($raw, '<iframe') === false) {
            return null;
        }
        $merged = $dataJson;
        $merged['map_combined_input'] = $raw;
        $parse = self::parseFromDataJson($merged);
        if (! $parse->ok || $parse->sourceKind !== MapSourceKind::Iframe) {
            return null;
        }
        if (MapDisplayMode::fromDataJson($merged) !== MapDisplayMode::ButtonOnly) {
            return null;
        }

        return MapDisplayMode::EmbedAndButton->value;
    }

    public static function parse(MapInputMode $mode, string $rawInput, ?MapProvider $declared): ContactMapParseResult
    {
        $raw = trim($rawInput);
        if ($raw === '') {
            return ContactMapParseResult::emptyInput();
        }
        if (strlen($raw) > self::COMBINED_INPUT_MAX_LENGTH) {
            return ContactMapParseResult::failure(['Слишком длинный текст. Попробуйте вставить только ссылку или код карты.']);
        }

        return match ($mode) {
            MapInputMode::Url => self::parseUrlOnly($raw, $declared),
            MapInputMode::Iframe => self::parseIframeFirst($raw, $declared, fallbackToUrl: true),
            MapInputMode::Auto => self::parseAuto($raw, $declared),
        };
    }

    private static function parseUrlOnly(string $raw, ?MapProvider $declared): ContactMapParseResult
    {
        $r = self::tryNormalizeUrl($raw, $declared);
        if ($r === null) {
            return ContactMapParseResult::failure([self::ERR_URL_ONLY_EXPECTED]);
        }
        if (! $r->ok) {
            return $r;
        }

        return $r;
    }

    private static function parseIframeFirst(string $raw, ?MapProvider $declared, bool $fallbackToUrl): ContactMapParseResult
    {
        $iframe = self::tryIframeToNormalized($raw, $declared);
        if ($iframe['ok'] ?? false) {
            return new ContactMapParseResult(
                isEmpty: false,
                ok: true,
                normalizedPublicUrl: $iframe['url'],
                detectedProvider: $iframe['provider'],
                sourceKind: MapSourceKind::Iframe,
                detectionLabelRu: null,
                usedSourceMessageRu: MapSourceKind::Iframe->usedSourceMessageRu(),
                errors: [],
                warnings: [],
            );
        }

        if (! empty($iframe['errors'])) {
            if ($fallbackToUrl) {
                $fb = self::tryPlainUrl($raw, $declared);
                if ($fb !== null) {
                    return $fb;
                }
            }

            return ContactMapParseResult::failure($iframe['errors']);
        }

        if ($fallbackToUrl) {
            $fb = self::tryPlainUrl($raw, $declared);
            if ($fb !== null) {
                return $fb;
            }
        }

        return ContactMapParseResult::failure([self::ERR_IFRAME_UNPARSEABLE]);
    }

    private static function parseAuto(string $raw, ?MapProvider $declared): ContactMapParseResult
    {
        $hasIframeTag = stripos($raw, '<iframe') !== false;

        if ($hasIframeTag) {
            $iframe = self::tryIframeToNormalized($raw, $declared);
            if ($iframe['ok'] ?? false) {
                return new ContactMapParseResult(
                    isEmpty: false,
                    ok: true,
                    normalizedPublicUrl: $iframe['url'],
                    detectedProvider: $iframe['provider'],
                    sourceKind: MapSourceKind::Iframe,
                    detectionLabelRu: self::LABEL_DETECTED_IFRAME,
                    usedSourceMessageRu: MapSourceKind::Iframe->usedSourceMessageRu(),
                    errors: [],
                    warnings: [],
                );
            }

            if (! empty($iframe['errors'])) {
                $fb = self::tryPlainUrl($raw, $declared);
                if ($fb !== null) {
                    return self::withDetectionLabel($fb, self::LABEL_DETECTED_URL);
                }

                return ContactMapParseResult::failure($iframe['errors']);
            }
        }

        $fb = self::tryPlainUrl($raw, $declared);
        if ($fb !== null) {
            return self::withDetectionLabel($fb, self::LABEL_DETECTED_URL);
        }

        if ($hasIframeTag) {
            return ContactMapParseResult::failure([self::ERR_IFRAME_UNPARSEABLE]);
        }

        return ContactMapParseResult::failure([
            'Поддерживаются только обычные https-ссылки на Яндекс Карты, Google Maps и 2ГИС.',
        ]);
    }

    private static function withDetectionLabel(ContactMapParseResult $inner, string $label): ContactMapParseResult
    {
        return new ContactMapParseResult(
            isEmpty: $inner->isEmpty,
            ok: $inner->ok,
            normalizedPublicUrl: $inner->normalizedPublicUrl,
            detectedProvider: $inner->detectedProvider,
            sourceKind: $inner->sourceKind,
            detectionLabelRu: $label,
            usedSourceMessageRu: $inner->usedSourceMessageRu,
            errors: $inner->errors,
            warnings: $inner->warnings,
        );
    }

    /**
     * @return array{ok: bool, url?: string, provider?: MapProvider, errors?: list<string>}
     */
    private static function tryIframeToNormalized(string $html, ?MapProvider $declared): array
    {
        $src = SafeMapPublicUrl::extractFirstIframeSrc($html);
        if ($src === null) {
            if (str_contains(strtolower($html), '<iframe')) {
                return ['ok' => false, 'errors' => [self::ERR_IFRAME_NO_SRC]];
            }

            return ['ok' => false, 'errors' => []];
        }

        $src = SafeMapPublicUrl::normalizeIframeSrcHttps($src);
        $r = self::tryNormalizeUrl($src, $declared);
        if ($r !== null) {
            if (! $r->ok) {
                return ['ok' => false, 'errors' => $r->errors];
            }

            return ['ok' => true, 'url' => $r->normalizedPublicUrl, 'provider' => $r->detectedProvider];
        }

        $parts = parse_url($src);
        $host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $detected = $host !== '' ? SafeMapPublicUrl::detectProviderForHost($host) : null;
        if ($detected === null || $detected === MapProvider::None) {
            return ['ok' => false, 'errors' => [self::ERR_IFRAME_UNTRUSTED]];
        }

        return ['ok' => true, 'url' => $src, 'provider' => $detected];
    }

    private static function tryPlainUrl(string $raw, ?MapProvider $declared): ?ContactMapParseResult
    {
        $candidates = [];
        $trim = trim($raw);
        if ($trim !== '' && ! str_contains($trim, '<')) {
            $candidates[] = $trim;
        }
        if (preg_match_all('#https://[^\s<>"\'\)\]\}]+#i', $raw, $matches) > 0) {
            foreach ($matches[0] as $m) {
                $u = rtrim($m, '.,;)\'"');
                if ($u !== '' && ! in_array($u, $candidates, true)) {
                    $candidates[] = $u;
                }
            }
        }
        $extracted = SafeMapPublicUrl::extractFirstHttpsUrl($raw);
        if ($extracted !== null && ! in_array($extracted, $candidates, true)) {
            $candidates[] = $extracted;
        }
        foreach (SafeMapPublicUrl::extractMapAnchorHrefs($raw) as $href) {
            if (! in_array($href, $candidates, true)) {
                $candidates[] = $href;
            }
        }
        usort($candidates, function (string $a, string $b): int {
            $sa = self::scoreMapUrlCandidate($a);
            $sb = self::scoreMapUrlCandidate($b);
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return strlen($b) <=> strlen($a);
        });
        foreach ($candidates as $candidate) {
            $r = self::tryNormalizeUrl($candidate, $declared);
            if ($r === null) {
                continue;
            }
            if (! $r->ok) {
                return $r;
            }

            return $r;
        }

        return null;
    }

    /**
     * Prefer embed/widget URLs over short marketing links inside the same HTML paste.
     */
    private static function scoreMapUrlCandidate(string $u): int
    {
        $l = strtolower($u);
        if (str_contains($l, 'map-widget') || str_contains($l, '/embed')) {
            return 0;
        }
        if (preg_match('#yandex\.ru/maps#i', $l) === 1) {
            return 1;
        }
        if (preg_match('#(google\.com/maps|maps\.google\.com|2gis\.ru|go\.2gis\.com)#i', $l) === 1) {
            return 2;
        }

        return 3;
    }

    /**
     * @return ContactMapParseResult|null null = not a map URL; ok=false = validation error (e.g. provider)
     */
    private static function tryNormalizeUrl(string $candidate, ?MapProvider $declared): ?ContactMapParseResult
    {
        $classified = SafeMapPublicUrl::normalizeAndClassify($candidate);
        if ($classified === null) {
            return null;
        }
        [$url, $fromUrl] = $classified;
        // Домен ссылки определяет провайдера; выпадающий список в редакторе может временно не совпадать при переключении типа.
        $provider = $declared !== null && $declared !== MapProvider::None && SafeMapPublicUrl::validateMatchesProvider($url, $declared)
            ? $declared
            : $fromUrl;

        return new ContactMapParseResult(
            isEmpty: false,
            ok: true,
            normalizedPublicUrl: $url,
            detectedProvider: $provider,
            sourceKind: MapSourceKind::Url,
            detectionLabelRu: null,
            usedSourceMessageRu: MapSourceKind::Url->usedSourceMessageRu(),
            errors: [],
            warnings: [],
        );
    }
}
