<?php

namespace App\Filament\Forms\Components;

use App\Rules\PublicAssetReference;
use App\Services\TenantFiles\TenantFileCatalogService;
use Closure;
use Filament\Forms\Components\Concerns\CanBeLengthConstrained;
use Filament\Forms\Components\Contracts\CanBeLengthConstrained as CanBeLengthConstrainedContract;
use Filament\Forms\Components\Field;
use InvalidArgumentException;

/**
 * Выбор / загрузка файла из tenant public storage (изображение или видео MP4/WebM) + ручной ввод того же state path.
 */
final class TenantPublicMediaPicker extends Field implements CanBeLengthConstrainedContract
{
    use CanBeLengthConstrained;

    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.tenant-public-media-picker';

    public const MEDIA_IMAGE = 'image';

    public const MEDIA_VIDEO = 'video';

    protected string|Closure $mediaType = self::MEDIA_IMAGE;

    protected bool|Closure $allowEmpty = true;

    protected string|Closure $uploadSlotSelector = '[data-tenant-public-upload-input]';

    protected string|Closure $uploadPublicSiteSubdirectory = 'page-builder';

    public function mediaType(string|Closure $type): static
    {
        $this->mediaType = $type;

        return $this;
    }

    public function getMediaType(): string
    {
        $v = (string) $this->evaluate($this->mediaType);
        if (! in_array($v, [self::MEDIA_IMAGE, self::MEDIA_VIDEO], true)) {
            throw new InvalidArgumentException('mediaType must be image or video.');
        }

        return $v;
    }

    public function getCatalogFilter(): string
    {
        return $this->getMediaType() === self::MEDIA_VIDEO
            ? TenantFileCatalogService::FILTER_VIDEOS
            : TenantFileCatalogService::FILTER_IMAGES;
    }

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules([
            function (): array {
                $when = $this->isEmptyAllowed() ? ['nullable'] : ['required'];
                $length = $this->getLengthValidationRules();

                return array_merge($when, $length, [new PublicAssetReference]);
            },
        ]);
    }
}
