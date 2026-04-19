<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Services\TenantFiles\TenantFileCatalogService;
use App\Tenant\StorageQuota\TenantStorageQuotaData;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use UnitEnum;

class TenantFilesPage extends Page
{
    protected static ?string $navigationLabel = 'Файлы сайта';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $title = 'Файлы темы и контента';

    protected static ?string $slug = 'tenant-files';

    protected static ?int $navigationSort = 15;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected string $view = 'filament.tenant.pages.tenant-files';

    public string $search = '';

    public string $filter = TenantFileCatalogService::FILTER_ALL;

    public int $filePage = 1;

    public int $filesPerPage = 30;

    public static function canAccess(): bool
    {
        if (\currentTenant() === null) {
            return false;
        }

        return Gate::allows('manage_pages')
            || Gate::allows('manage_homepage')
            || Gate::allows('manage_settings');
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'tenantFilesWhatIs',
                [
                    'Каталог файлов в публичном хранилище тенанта: поиск, фильтр по типу, постраничный просмотр.',
                    '',
                    'Учитывайте квоту при удалении или загрузке крупных медиа.',
                ],
                'Справка по файлам сайта',
            ),
        ];
    }

    #[Computed]
    public function lightCatalogRows(): array
    {
        $t = \currentTenant();
        if ($t === null) {
            return [];
        }
        $q = trim($this->search);

        return app(TenantFileCatalogService::class)->listLightForTenant(
            (int) $t->id,
            $this->filter,
            $q !== '' ? $q : null,
        );
    }

    #[Computed]
    public function fileCatalogTotal(): int
    {
        return count($this->lightCatalogRows);
    }

    #[Computed]
    public function fileCatalogLastPage(): int
    {
        $total = $this->fileCatalogTotal;
        if ($total === 0) {
            return 1;
        }

        return (int) max(1, (int) ceil($total / max(1, $this->filesPerPage)));
    }

    /**
     * Метаданные (размер, дата) подгружаются только для текущей страницы списка.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function catalogRows(): array
    {
        $t = \currentTenant();
        if ($t === null) {
            return [];
        }

        $all = $this->lightCatalogRows;
        $page = max(1, min($this->filePage, $this->fileCatalogLastPage));

        $offset = ($page - 1) * max(1, $this->filesPerPage);
        $slice = array_slice($all, $offset, max(1, $this->filesPerPage));

        return app(TenantFileCatalogService::class)->hydrateFileMetadata((int) $t->id, $slice);
    }

    #[Computed]
    public function storageQuota(): ?TenantStorageQuotaData
    {
        $t = \currentTenant();
        if ($t === null) {
            return null;
        }

        return app(TenantStorageQuotaService::class)->forTenant($t);
    }

    public function gotoFilePage(int $page): void
    {
        $this->filePage = max(1, $page);
        unset($this->catalogRows);
    }

    public function updatedSearch(): void
    {
        $this->filePage = 1;
        unset($this->lightCatalogRows);
        unset($this->catalogRows);
    }

    public function updatedFilter(): void
    {
        $this->filePage = 1;
        unset($this->lightCatalogRows);
        unset($this->catalogRows);
    }
}
