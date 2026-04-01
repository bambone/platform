<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Как {@see SpatieMediaLibraryFileUpload}, но URL для уже сохранённых файлов ведёт на same-origin stream,
 * иначе fetch() превью в админке блокируется CORS при {@code AWS_URL} / R2 на другом хосте.
 */
final class TenantSpatieMediaLibraryFileUpload extends SpatieMediaLibraryFileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getUploadedFileUsing(function (SpatieMediaLibraryFileUpload $component, string $file): ?array {
            if (! $component->getRecord()) {
                return null;
            }

            /** @var ?Media $media */
            $media = $component->getRecord()->getRelationValue('media')->firstWhere('uuid', $file);

            $url = null;

            if ($component->getVisibility() === 'private') {
                $conversion = $component->getConversion();

                try {
                    $url = $media?->getTemporaryUrl(
                        now()->addMinutes(30)->endOfHour(),
                        (filled($conversion) && $media->hasGeneratedConversion($conversion)) ? $conversion : '',
                    );
                } catch (Throwable) {
                }
            }

            if ($url === null && $component->getVisibility() === 'public' && $media !== null) {
                $conversion = $component->getConversion();
                $conv = (filled($conversion) && $media->hasGeneratedConversion($conversion)) ? $conversion : '';
                $url = filament_tenant_spatie_media_preview_url($media, $conv);
            }

            if ($url === null && $component->getConversion() && $media?->hasGeneratedConversion($component->getConversion())) {
                $url = $media->getUrl($component->getConversion());
            }

            $url ??= $media?->getUrl();

            return [
                'name' => $media?->getAttributeValue('name') ?? $media?->getAttributeValue('file_name'),
                'size' => $media?->getAttributeValue('size'),
                'type' => $media?->getAttributeValue('mime_type'),
                'url' => $url,
            ];
        });
    }
}
