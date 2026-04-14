<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

/**
 * Public map block: single effective render mode for Blade + embed/button flags for admin preview.
 */
final readonly class ContactMapResolvedView
{
    public function __construct(
        public MapEffectiveRenderMode $mapEffectiveRenderMode,
        public string $mapPublicUrl,
        public string $mapEmbedUrl,
        public string $mapTitle,
        public string $mapProviderLabel,
        public string $mapActionLabel,
        public MapProviderSupportsEmbedState $mapProviderSupportsEmbedState,
        public bool $mapCanEmbed,
        public bool $mapWillRenderEmbed,
        public bool $mapWillRenderButton,
        public ?string $mapFallbackReasonRu,
        public ?string $mapSecondaryPublicUrl,
        public ?string $mapSecondaryActionLabel,
        public bool $mapWillRenderSecondaryButton,
    ) {}

    public function shouldRenderMapBlock(): bool
    {
        if ($this->mapEffectiveRenderMode !== MapEffectiveRenderMode::None) {
            return true;
        }

        return $this->mapWillRenderSecondaryButton;
    }
}
