<?php

namespace App\Filament\Forms\Components;

use App\Rules\PublicAssetReference;
use Closure;
use Filament\Forms\Components\Field;

final class TenantPublicImagePicker extends Field
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.tenant-public-image-picker';

    protected bool|Closure $allowEmpty = true;

    /** CSS selector единственного скрытого file input на этом Livewire-компоненте */
    protected string|Closure $uploadSlotSelector = '[data-tenant-public-upload-input]';

    /** Путь под {@see TenantStorageArea::PublicSite} для новых загрузок, напр. {@code page-builder} или {@code site/logo}. */
    protected string|Closure $uploadPublicSiteSubdirectory = 'page-builder';

    /**
     * Относительный путь внутри bundled-темы (как в {@see theme_platform_asset_url()}), например {@code marketing/hero-bg.png}.
     * Если state пустой, в превью показывается этот ассет темы; «Очистить» по-прежнему только при своём override (object key / URL в state).
     */
    protected string|Closure|null $themeFallbackPreviewPath = null;

    public function allowEmpty(bool|Closure $allow = true): static
    {
        $this->allowEmpty = $allow;

        return $this;
    }

    public function uploadSlotSelector(string|Closure $selector): static
    {
        $this->uploadSlotSelector = $selector;

        return $this;
    }

    public function getUploadSlotSelector(): string
    {
        return (string) $this->evaluate($this->uploadSlotSelector);
    }

    public function uploadPublicSiteSubdirectory(string|Closure $path): static
    {
        $this->uploadPublicSiteSubdirectory = $path;

        return $this;
    }

    public function getUploadPublicSiteSubdirectory(): string
    {
        $v = trim((string) $this->evaluate($this->uploadPublicSiteSubdirectory), '/');

        return $v !== '' ? $v : 'page-builder';
    }

    public function isEmptyAllowed(): bool
    {
        return (bool) $this->evaluate($this->allowEmpty);
    }

    public function themeFallbackPreviewPath(string|Closure|null $relativeWithinTheme): static
    {
        $this->themeFallbackPreviewPath = $relativeWithinTheme;

        return $this;
    }

    public function getThemeFallbackPreviewPath(): ?string
    {
        if ($this->themeFallbackPreviewPath === null) {
            return null;
        }
        $v = trim((string) $this->evaluate($this->themeFallbackPreviewPath), '/');

        return $v !== '' ? $v : null;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules([
            function (): array {
                $rules = [new PublicAssetReference];
                if (! $this->isEmptyAllowed()) {
                    array_unshift($rules, 'required');
                } else {
                    array_unshift($rules, 'nullable');
                }

                return $rules;
            },
        ]);
    }
}
