<?php

namespace App\Livewire\Concerns;

use App\Services\TenantFiles\TenantFileCatalogService;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageArea;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Модалка выбора файла из каталога tenant + буфер загрузки изображения в {@code site/page-builder}.
 *
 * Требует на компоненте {@see WithFileUploads} и {@see currentTenant()}.
 */
trait InteractsWithTenantPublicFilePicker
{
    public bool $tenantPublicFilePickerOpen = false;

    public string $tenantPublicFilePickerTargetPath = '';

    public string $tenantPublicFilePickerFilter = TenantFileCatalogService::FILTER_ALL;

    public string $tenantPublicFilePickerSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $tenantPublicFilePickerRows = [];

    public ?TemporaryUploadedFile $tenantPublicImageUploadBuffer = null;

    public string $tenantPublicImageUploadTargetPath = '';

    public string $tenantPublicImageUploadSubdirectory = 'page-builder';

    public function openTenantPublicFilePicker(string $absoluteStatePath, string $filter = TenantFileCatalogService::FILTER_ALL): void
    {
        $this->tenantPublicFilePickerTargetPath = $absoluteStatePath;
        $this->tenantPublicFilePickerFilter = $filter;
        $this->tenantPublicFilePickerSearch = '';
        $this->refreshTenantPublicFilePickerRows();
        $this->tenantPublicFilePickerOpen = true;
    }

    public function closeTenantPublicFilePicker(): void
    {
        $this->tenantPublicFilePickerOpen = false;
        $this->tenantPublicFilePickerTargetPath = '';
        $this->tenantPublicFilePickerRows = [];
    }

    public function updatedTenantPublicFilePickerSearch(): void
    {
        $this->refreshTenantPublicFilePickerRows();
    }

    public function updatedTenantPublicFilePickerFilter(): void
    {
        $this->refreshTenantPublicFilePickerRows();
    }

    public function pickTenantPublicFile(string $objectKey): void
    {
        $t = \currentTenant();
        if ($t === null || $this->tenantPublicFilePickerTargetPath === '') {
            $this->closeTenantPublicFilePicker();

            return;
        }
        if (! app(TenantFileCatalogService::class)->isAllowedObjectKey((int) $t->id, $objectKey)) {
            $this->closeTenantPublicFilePicker();

            return;
        }
        $this->assignToLivewireRootState($this->tenantPublicFilePickerTargetPath, $objectKey);
        $this->closeTenantPublicFilePicker();
    }

    public function clearTenantPublicImageField(string $absoluteStatePath): void
    {
        $this->assignToLivewireRootState($absoluteStatePath, '');
    }

    public function updatedTenantPublicImageUploadBuffer(): void
    {
        if ($this->tenantPublicImageUploadBuffer === null || $this->tenantPublicImageUploadTargetPath === '') {
            return;
        }
        $t = \currentTenant();
        if ($t === null) {
            $this->tenantPublicImageUploadBuffer = null;

            return;
        }
        $this->validate([
            'tenantPublicImageUploadBuffer' => ['required', 'image', 'max:4096'],
        ]);
        $disk = TenantStorageDisks::publicDiskName();
        $sub = trim($this->tenantPublicImageUploadSubdirectory, '/');
        if ($sub === '') {
            $sub = 'page-builder';
        }
        $dirKey = TenantStorage::for($t)->publicPathInArea(TenantStorageArea::PublicSite, $sub);
        $ext = strtolower($this->tenantPublicImageUploadBuffer->getClientOriginalExtension() ?: 'bin');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            $this->tenantPublicImageUploadBuffer = null;

            return;
        }
        $name = Str::uuid()->toString().'.'.$ext;
        Storage::disk($disk)->putFileAs($dirKey, $this->tenantPublicImageUploadBuffer, $name);
        $objectKey = $dirKey.'/'.$name;
        $this->assignToLivewireRootState($this->tenantPublicImageUploadTargetPath, $objectKey);
        $this->tenantPublicImageUploadBuffer = null;
        $this->tenantPublicImageUploadTargetPath = '';
        $this->tenantPublicImageUploadSubdirectory = 'page-builder';
    }

    public function prepareTenantPublicImageUpload(string $absoluteStatePath, string $relativeUnderPublicSite = 'page-builder'): void
    {
        $this->tenantPublicImageUploadTargetPath = $absoluteStatePath;
        $this->tenantPublicImageUploadSubdirectory = trim($relativeUnderPublicSite, '/') !== ''
            ? trim($relativeUnderPublicSite, '/')
            : 'page-builder';
    }

    protected function refreshTenantPublicFilePickerRows(): void
    {
        $t = \currentTenant();
        if ($t === null) {
            $this->tenantPublicFilePickerRows = [];

            return;
        }
        $search = $this->tenantPublicFilePickerSearch !== '' ? $this->tenantPublicFilePickerSearch : null;
        $this->tenantPublicFilePickerRows = app(TenantFileCatalogService::class)->listForTenant(
            (int) $t->id,
            $this->tenantPublicFilePickerFilter,
            $search,
        );
    }

    protected function assignToLivewireRootState(string $absolutePath, mixed $value): void
    {
        if ($absolutePath === '' || ! str_contains($absolutePath, '.')) {
            return;
        }
        $dot = strpos($absolutePath, '.');
        $root = substr($absolutePath, 0, $dot);
        $rest = substr($absolutePath, $dot + 1);
        if (! property_exists($this, $root)) {
            return;
        }
        $target = &$this->{$root};
        if (! is_array($target)) {
            return;
        }
        data_set($target, $rest, $value);
        $this->{$root} = $target;
    }
}
