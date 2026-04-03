<?php

namespace App\Filament\Tenant\Pages;

use App\Services\TenantFiles\TenantFileCatalogService;
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

    public static function canAccess(): bool
    {
        if (\currentTenant() === null) {
            return false;
        }

        return Gate::allows('manage_pages')
            || Gate::allows('manage_homepage')
            || Gate::allows('manage_settings');
    }

    #[Computed]
    public function catalogRows(): array
    {
        $t = \currentTenant();
        if ($t === null) {
            return [];
        }
        $q = trim($this->search);

        return app(TenantFileCatalogService::class)->listForTenant(
            (int) $t->id,
            $this->filter,
            $q !== '' ? $q : null,
        );
    }

    public function updatedSearch(): void
    {
        unset($this->catalogRows);
    }

    public function updatedFilter(): void
    {
        unset($this->catalogRows);
    }
}
