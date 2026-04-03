<?php

namespace App\Filament\Forms\Components;

use App\Models\Tenant;
use App\Tenant\StorageQuota\StorageQuotaExceededException;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
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

        $this->saveUploadedFileUsing(function (SpatieMediaLibraryFileUpload $component, TemporaryUploadedFile $file, ?Model $record): ?string {
            $tenant = currentTenant();
            if ($tenant instanceof Tenant && TenantStorageQuotaService::isQuotaEnforcementActive()) {
                try {
                    app(TenantStorageQuotaService::class)->assertCanStoreBytes($tenant, (int) $file->getSize(), 'media_upload');
                } catch (StorageQuotaExceededException $e) {
                    throw ValidationException::withMessages([
                        $component->getStatePath() => [$e->getMessage()],
                    ]);
                }
            }

            if (! method_exists($record, 'addMediaFromString')) {
                return $file;
            }

            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (UnableToCheckFileExistence $exception) {
                return null;
            }

            /** @var FileAdder $mediaAdder */
            $mediaAdder = $record->addMediaFromString($file->get());

            $filename = $component->getUploadedFileNameForStorage($file);

            $media = $mediaAdder
                ->addCustomHeaders([...['ContentType' => $file->getMimeType()], ...$component->getCustomHeaders()])
                ->usingFileName($filename)
                ->usingName($component->getMediaName($file) ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->storingConversionsOnDisk($component->getConversionsDisk() ?? '')
                ->withCustomProperties($component->getCustomProperties($file))
                ->withManipulations($component->getManipulations())
                ->withResponsiveImagesIf($component->hasResponsiveImages())
                ->withProperties($component->getProperties())
                ->toMediaCollection($component->getCollection() ?? 'default', $component->getDiskName());

            return $media->getAttributeValue('uuid');
        });

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
