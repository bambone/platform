<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Jobs\RecalculateTenantStorageUsageJob;
use App\Services\TenantFiles\TenantFileCatalogService;
use App\Services\TenantFiles\TenantPublicFileReferenceFinder;
use App\Support\Storage\TenantPublicMediaWriter;
use App\Tenant\StorageQuota\TenantStorageQuotaData;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        return Gate::allows('manage_tenant_files');
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'tenantFilesWhatIs',
                [
                    'Каталог файлов в публичном хранилище тенанта: поиск, фильтр по типу, постраничный просмотр.',
                    'Доступ и удаление — только с правом «Файлы в storage»; папка темы (themes) только для просмотра, из UI не удаляется.',
                    'Перед удалением проверяются ссылки в настройках, секциях страниц, услугах и каталоге медиа в БД; при совпадениях удаление блокируется.',
                    'Пересчёт квоты после удаления выполняется в фоне; цифры в мониторинге могут обновиться с задержкой.',
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

    /**
     * Только Gate + логика префикса на диске; без БД. Не добавлять сюда тяжёлые проверки — вызывается на каждую строку таблицы.
     */
    public function isCatalogRowDeletable(string $objectKey): bool
    {
        $t = \currentTenant();
        if ($t === null) {
            return false;
        }
        if (! Gate::allows('manage_tenant_files')) {
            return false;
        }

        return app(TenantFileCatalogService::class)->isDeletableObjectKey((int) $t->id, $objectKey);
    }

    public function deleteFile(string $objectKey): void
    {
        $t = \currentTenant();
        if ($t === null) {
            return;
        }
        if (! Gate::allows('manage_tenant_files')) {
            Notification::make()
                ->title(__('Нет доступа'))
                ->danger()
                ->send();

            return;
        }

        $objectKey = str_replace('\\', '/', trim($objectKey));
        if ($objectKey === '' || str_contains($objectKey, '..')) {
            Notification::make()
                ->title(__('Некорректный путь'))
                ->danger()
                ->send();

            return;
        }

        if (! app(TenantFileCatalogService::class)->isAllowedObjectKey((int) $t->id, $objectKey)) {
            Notification::make()
                ->title(__('Удаление этого пути запрещено'))
                ->body(__('Разрешены только зоны site, themes и media в публичном хранилище тенанта.'))
                ->danger()
                ->send();

            return;
        }

        if (! app(TenantFileCatalogService::class)->isDeletableObjectKey((int) $t->id, $objectKey)) {
            Notification::make()
                ->title(__('Удаление из этой папки недоступно'))
                ->body(__('Ассеты темы (themes) в интерфейсе только для просмотра. Удаляйте через релиз/деплой или обратитесь к разработчику.'))
                ->warning()
                ->send();

            return;
        }

        $refs = app(TenantPublicFileReferenceFinder::class)->findReferenceLabels((int) $t->id, $objectKey);
        if ($refs !== []) {
            $body = collect($refs)->take(12)->implode("\n");
            if (count($refs) > 12) {
                $body .= "\n…";
            }
            Notification::make()
                ->title(__('Удаление отменено: файл используется'))
                ->body($body)
                ->warning()
                ->send();

            return;
        }

        $ok = app(TenantPublicMediaWriter::class)->deletePublicObjectKey((int) $t->id, $objectKey);
        $userId = Auth::id();
        $userId = $userId === null ? null : (int) $userId;
        if ($ok) {
            Log::info('tenant_public_file_deleted', [
                'tenant_id' => (int) $t->id,
                'user_id' => $userId,
                'object_key' => $objectKey,
                'result' => 'deleted',
            ]);
            try {
                RecalculateTenantStorageUsageJob::dispatch((int) $t->id);
            } catch (\Throwable $e) {
                Log::warning('tenant_storage_quota_recalculate_dispatch_failed', [
                    'tenant_id' => (int) $t->id,
                    'user_id' => $userId,
                    'after_object_key' => $objectKey,
                    'message' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('tenant_public_file_deleted', [
                'tenant_id' => (int) $t->id,
                'user_id' => $userId,
                'object_key' => $objectKey,
                'result' => 'failed',
            ]);
        }

        unset($this->lightCatalogRows, $this->catalogRows, $this->fileCatalogTotal, $this->fileCatalogLastPage, $this->storageQuota);

        if ($ok) {
            Notification::make()
                ->title(__('Файл удалён'))
                ->body(__('Квота в мониторинге обновится в фоне (обычно в течение короткого времени).'))
                ->success()
                ->send();

            $this->filePage = min($this->filePage, max(1, $this->fileCatalogLastPage));
        } else {
            Notification::make()
                ->title(__('Не удалось удалить'))
                ->body(__('Повторите попытку или проверьте лог; при dual-write с R2 удаление могло поставить задачу в очередь репликации.'))
                ->danger()
                ->send();
        }
    }
}
