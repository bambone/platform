<?php

namespace App\Filament\Forms\Components;

use App\Rules\PublicAssetReference;
use Closure;
use Filament\Forms\Components\Concerns\CanBeLengthConstrained;
use Filament\Forms\Components\Contracts\CanBeLengthConstrained as CanBeLengthConstrainedContract;
use Filament\Forms\Components\Field;

/**
 * Обложка для Expert: Галерея — для «Видео (файл)» тот же UX, что {@see TenantPublicMediaPicker} (изображение);
 * для «Видео (встраивание)» — отдельный сценарий без «Указать вручную». Одно поле {@code poster_url}.
 */
final class TenantPublicEditorialGalleryPoster extends Field implements CanBeLengthConstrainedContract
{
    use CanBeLengthConstrained;

    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.tenant-public-editorial-gallery-poster';

    protected string|Closure $uploadPublicSiteSubdirectory = 'page-builder/editorial-gallery/posters';

    protected string|Closure $uploadSlotSelector = '[data-tenant-public-upload-input]';

    public function uploadPublicSiteSubdirectory(string|Closure $path): static
    {
        $this->uploadPublicSiteSubdirectory = $path;

        return $this;
    }

    public function getUploadPublicSiteSubdirectory(): string
    {
        $v = trim((string) $this->evaluate($this->uploadPublicSiteSubdirectory), '/');

        return $v !== '' ? $v : 'page-builder/editorial-gallery/posters';
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules([
            function (): array {
                $length = $this->getLengthValidationRules();

                return array_merge(['nullable'], $length, [new PublicAssetReference]);
            },
        ]);
    }
}
