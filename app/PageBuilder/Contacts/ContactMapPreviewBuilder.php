<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

use Filament\Schemas\Components\Utilities\Get;

/**
 * Inline status + preview for Filament (same resolver as public site).
 */
final class ContactMapPreviewBuilder
{
    /**
     * @return array{status: 'error'|'warning'|'success'|'empty', message: string, resolved: ?ContactMapResolvedView}
     */
    public static function fromGet(Get $get): array
    {
        $data = $get('data_json');
        $data = is_array($data) ? $data : [];

        return self::fromDataJson($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: 'error'|'warning'|'success'|'empty', message: string, resolved: ?ContactMapResolvedView}
     */
    public static function fromDataJson(array $data): array
    {
        $mapOn = ! array_key_exists('map_enabled', $data) || self::truthy($data['map_enabled']);
        $provider = MapProvider::tryFromMixed($data['map_provider'] ?? '');

        if (! $mapOn) {
            return [
                'status' => 'empty',
                'message' => 'Карта выключена.',
                'resolved' => null,
            ];
        }

        $rawCombined = ContactMapSourceParser::rawCombinedInput($data);
        $rawSecondary = ContactMapSourceParser::rawSecondaryCombinedInput($data);

        if (($provider === null || $provider === MapProvider::None) && $rawCombined === '' && $rawSecondary === '') {
            return [
                'status' => 'empty',
                'message' => 'Включите карту и выберите провайдера, затем вставьте ссылку или код карты.',
                'resolved' => null,
            ];
        }

        if ($rawCombined === '') {
            return self::previewSecondaryOnlyOrEmptyPrimary($data, $rawSecondary);
        }

        $parse = ContactMapSourceParser::parseFromDataJson($data);
        if (! $parse->ok) {
            $msg = $parse->errors[0] ?? 'Не удалось разобрать ввод.';

            return [
                'status' => 'error',
                'message' => $msg,
                'resolved' => null,
            ];
        }

        $dataForResolve = $data;
        $dataForResolve['map_public_url'] = $parse->normalizedPublicUrl;
        $dataForResolve['map_provider'] = $parse->detectedProvider->value;

        $resolved = app(ContactMapPublicResolver::class)->resolve($dataForResolve);

        $displayMode = MapDisplayMode::fromDataJson($data);
        $wantsEmbed = in_array($displayMode, [MapDisplayMode::EmbedOnly, MapDisplayMode::EmbedAndButton], true);
        $hasEmbed = $resolved->mapCanEmbed && $resolved->mapWillRenderEmbed;

        $prefixLines = [];
        if ($parse->detectionLabelRu !== null && $parse->detectionLabelRu !== '') {
            $prefixLines[] = $parse->detectionLabelRu;
        }
        if ($parse->usedSourceMessageRu !== null && $parse->usedSourceMessageRu !== '') {
            $prefixLines[] = $parse->usedSourceMessageRu;
        }
        $prefix = $prefixLines !== [] ? implode(' ', $prefixLines).' ' : '';

        // 2ГИС: обычная ссылка «Поделиться» — link-first; встраивание только через embed.2gis.com (отдельный сценарий).
        if ($parse->detectedProvider === MapProvider::TwoGis && ! $resolved->mapCanEmbed) {
            return [
                'status' => 'success',
                'message' => self::appendSecondaryHint(
                    $prefix.'Провайдер: 2ГИС. Встроенная карта из обычной ссылки «Поделиться» в текущей интеграции не поддерживается. На сайте будет кнопка «'.$resolved->mapActionLabel.'».',
                    $resolved,
                ),
                'resolved' => $resolved,
            ];
        }

        if ($displayMode === MapDisplayMode::ButtonOnly && $resolved->mapCanEmbed) {
            return [
                'status' => 'warning',
                'message' => self::appendSecondaryHint(
                    $prefix.'Выбран режим «Только ссылка (кнопка)» — встроенная карта не показывается. Чтобы увидеть карту в предпросмотре и на сайте, выберите «Только карта» или «Карта и кнопка».',
                    $resolved,
                ),
                'resolved' => $resolved,
            ];
        }

        if ($wantsEmbed && ! $hasEmbed && $resolved->mapFallbackReasonRu !== null) {
            return [
                'status' => 'warning',
                'message' => self::appendSecondaryHint($prefix.trim($resolved->mapFallbackReasonRu), $resolved),
                'resolved' => $resolved,
            ];
        }

        if ($wantsEmbed && ! $hasEmbed) {
            return [
                'status' => 'warning',
                'message' => self::appendSecondaryHint(
                    $prefix.'Ссылка откроется корректно, но встроенная карта для неё недоступна. На сайте будет только кнопка.',
                    $resolved,
                ),
                'resolved' => $resolved,
            ];
        }

        $modeLabel = match ($resolved->mapEffectiveRenderMode) {
            MapEffectiveRenderMode::ButtonOnly => 'только кнопка',
            MapEffectiveRenderMode::EmbedOnly => 'только карта',
            MapEffectiveRenderMode::EmbedAndButton => 'карта и кнопка',
            MapEffectiveRenderMode::None => 'нет',
        };

        return [
            'status' => 'success',
            'message' => self::appendSecondaryHint($prefix.'На сайте будет: '.$modeLabel.'.', $resolved),
            'resolved' => $resolved,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: 'error'|'warning'|'success'|'empty', message: string, resolved: ?ContactMapResolvedView}
     */
    private static function previewSecondaryOnlyOrEmptyPrimary(array $data, string $rawSecondary): array
    {
        $resolved = app(ContactMapPublicResolver::class)->resolve($data);

        if ($rawSecondary === '') {
            return [
                'status' => 'empty',
                'message' => 'Вставьте ссылку или код карты — система сама определит формат. После этого появится проверка и предпросмотр.',
                'resolved' => $resolved,
            ];
        }

        $secParse = ContactMapSourceParser::parse(MapInputMode::Auto, $rawSecondary, null);
        if ($secParse->isEmpty) {
            return [
                'status' => 'empty',
                'message' => 'Вставьте ссылку или код карты — система сама определит формат. После этого появится проверка и предпросмотр.',
                'resolved' => $resolved,
            ];
        }
        if (! $secParse->ok) {
            return [
                'status' => 'error',
                'message' => $secParse->errors[0] ?? 'Не удалось разобрать дополнительную ссылку на карту.',
                'resolved' => null,
            ];
        }

        if ($resolved->mapWillRenderSecondaryButton) {
            $label = $resolved->mapSecondaryActionLabel ?? 'Открыть';

            return [
                'status' => 'success',
                'message' => 'Только дополнительная карта: на сайте будет кнопка «'.$label.'».',
                'resolved' => $resolved,
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Дополнительная ссылка на карту не распознана или совпадает с основной.',
            'resolved' => $resolved,
        ];
    }

    private static function appendSecondaryHint(string $message, ContactMapResolvedView $resolved): string
    {
        if (! $resolved->mapWillRenderSecondaryButton || $resolved->mapSecondaryActionLabel === null || $resolved->mapSecondaryActionLabel === '') {
            return $message;
        }

        return $message.' Дополнительно: кнопка «'.$resolved->mapSecondaryActionLabel.'».';
    }

    private static function truthy(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if ($v === 1 || $v === '1' || $v === 'true') {
            return true;
        }

        return false;
    }
}
