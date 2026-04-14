<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

use App\Support\SafeMapPublicUrl;

/**
 * Resolves effective map presentation for public site and admin preview (same rules).
 */
final class ContactMapPublicResolver
{
    /**
     * @param  array<string, mixed>  $dataJson
     */
    public function resolve(array $dataJson): ContactMapResolvedView
    {
        $canonical = ContactMapCanonical::fromDataJson($dataJson);

        $title = trim($canonical->mapTitle);
        if ($title === '' && $canonical->hasVisibleMap()) {
            $title = 'Адрес и карта';
        }

        $primaryUrl = $canonical->mapPublicUrl;
        $secondaryUrl = $canonical->mapSecondaryPublicUrl;
        $hasPrimary = $primaryUrl !== '';
        $hasSecondary = $secondaryUrl !== '';

        $provider = $canonical->mapProvider;
        $providerLabel = $provider->labelRu();
        $actionLabel = $provider->actionLabelRu();
        $providerState = MapProviderSupportsEmbedState::forProvider($provider);

        if (! $canonical->hasVisibleMap()) {
            return new ContactMapResolvedView(
                mapEffectiveRenderMode: MapEffectiveRenderMode::None,
                mapPublicUrl: '',
                mapEmbedUrl: '',
                mapTitle: $title,
                mapProviderLabel: $providerLabel,
                mapActionLabel: $actionLabel,
                mapProviderSupportsEmbedState: $providerState,
                mapCanEmbed: false,
                mapWillRenderEmbed: false,
                mapWillRenderButton: false,
                mapFallbackReasonRu: null,
                mapSecondaryPublicUrl: null,
                mapSecondaryActionLabel: null,
                mapWillRenderSecondaryButton: false,
            );
        }

        $displayMode = $canonical->mapDisplayMode;

        $embedSrc = null;
        $canEmbedThisUrl = false;
        $fallbackReason = null;
        $effective = MapEffectiveRenderMode::None;
        $willEmbed = false;
        $willButton = false;

        if ($hasPrimary) {
            [$embedSrc, $canEmbedThisUrl] = SafeMapEmbedResolver::resolveEmbedSrc($provider, $primaryUrl);

            $hadWidgetPath = str_contains(strtolower($primaryUrl), 'map-widget')
                || str_contains(strtolower($primaryUrl), '/maps/embed')
                || str_contains(strtolower($primaryUrl), 'embed.2gis.com');
            $fallbackReason = ! $canEmbedThisUrl
                ? SafeMapEmbedResolver::fallbackReasonWhenNoEmbedRu($provider, $hadWidgetPath)
                : null;

            $effective = $this->computeEffectiveMode($displayMode, $canEmbedThisUrl);

            $willEmbed = in_array($effective, [MapEffectiveRenderMode::EmbedOnly, MapEffectiveRenderMode::EmbedAndButton], true);
            $willButton = in_array($effective, [MapEffectiveRenderMode::ButtonOnly, MapEffectiveRenderMode::EmbedAndButton], true);
        }

        $embedForView = ($willEmbed && $embedSrc !== null) ? $embedSrc : '';

        [$secOut, $secLabel, $willSec] = $this->resolveSecondary($primaryUrl, $secondaryUrl);

        return new ContactMapResolvedView(
            mapEffectiveRenderMode: $effective,
            mapPublicUrl: $primaryUrl,
            mapEmbedUrl: $embedForView,
            mapTitle: $title,
            mapProviderLabel: $providerLabel,
            mapActionLabel: $actionLabel,
            mapProviderSupportsEmbedState: $providerState,
            mapCanEmbed: $canEmbedThisUrl,
            mapWillRenderEmbed: $willEmbed,
            mapWillRenderButton: $willButton,
            mapFallbackReasonRu: $fallbackReason !== '' ? $fallbackReason : null,
            mapSecondaryPublicUrl: $secOut,
            mapSecondaryActionLabel: $secLabel,
            mapWillRenderSecondaryButton: $willSec,
        );
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: bool}
     */
    private function resolveSecondary(string $primaryUrl, string $secondaryUrl): array
    {
        if ($secondaryUrl === '') {
            return [null, null, false];
        }
        $classified = SafeMapPublicUrl::normalizeAndClassify($secondaryUrl);
        if ($classified === null) {
            return [null, null, false];
        }
        [$norm, $fromProvider] = $classified;
        if ($primaryUrl !== '' && $norm === $primaryUrl) {
            return [null, null, false];
        }

        return [$norm, $fromProvider->actionLabelRu(), true];
    }

    private function computeEffectiveMode(MapDisplayMode $displayMode, bool $canEmbed): MapEffectiveRenderMode
    {
        if ($displayMode === MapDisplayMode::ButtonOnly) {
            return MapEffectiveRenderMode::ButtonOnly;
        }
        if (! $canEmbed) {
            return MapEffectiveRenderMode::ButtonOnly;
        }

        return match ($displayMode) {
            MapDisplayMode::EmbedOnly => MapEffectiveRenderMode::EmbedOnly,
            MapDisplayMode::EmbedAndButton => MapEffectiveRenderMode::EmbedAndButton,
            MapDisplayMode::ButtonOnly => MapEffectiveRenderMode::ButtonOnly,
        };
    }
}
