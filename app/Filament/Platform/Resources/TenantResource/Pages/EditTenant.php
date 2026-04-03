<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Shared\TenantAnalyticsFormSchema;
use App\Jobs\RecalculateTenantStorageUsageJob;
use App\Models\User;
use App\Services\Analytics\AnalyticsSettingsPersistence;
use App\Support\Analytics\AnalyticsSettingsFormMapper;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @var array<string, mixed>
     */
    protected array $pendingAnalyticsForm = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculateTenantStorage')
                ->label('Пересчитать хранилище')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->canEditTenantStorage())
                ->requiresConfirmation()
                ->action(function (): void {
                    RecalculateTenantStorageUsageJob::dispatchSync((int) $this->record->id);
                    $this->record->refresh();
                    $this->record->load('storageQuota');
                    Notification::make()
                        ->title('Использование хранилища пересчитано')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('editExtraStorageQuota')
                ->label('Доп. квота (МБ)')
                ->icon('heroicon-o-server-stack')
                ->visible(fn (): bool => $this->canEditTenantStorage())
                ->modalHeading('Дополнительная квота')
                ->modalDescription(function (): string {
                    $q = $this->record->storageQuota ?? app(TenantStorageQuotaService::class)->ensureQuotaRecord($this->record);

                    return 'Текущее использование: '.Number::fileSize((int) $q->used_bytes, precision: 2).'; эффективный лимит: '.Number::fileSize($q->effective_quota_bytes, precision: 2).'.';
                })
                ->fillForm(function (): array {
                    $q = $this->record->storageQuota ?? app(TenantStorageQuotaService::class)->ensureQuotaRecord($this->record);

                    return [
                        'extra_mb' => round(((int) $q->extra_quota_bytes) / (1024 * 1024), 3),
                    ];
                })
                ->form([
                    TextInput::make('extra_mb')
                        ->label('Дополнительно (МБ)')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $bytes = (int) round(((float) $data['extra_mb']) * 1024 * 1024);
                    app(TenantStorageQuotaService::class)->setExtraQuotaBytes($this->record, max(0, $bytes), Auth::id() ? (int) Auth::id() : null);
                    $this->record->refresh();
                    $this->record->load('storageQuota');
                    Notification::make()->title('Дополнительная квота обновлена')->success()->send();
                }),
            Actions\Action::make('editStoragePackageLabel')
                ->label('Пакет хранилища')
                ->icon('heroicon-o-tag')
                ->visible(fn (): bool => $this->canEditTenantStorage())
                ->fillForm(function (): array {
                    $q = $this->record->storageQuota ?? app(TenantStorageQuotaService::class)->ensureQuotaRecord($this->record);

                    return [
                        'storage_package_label' => (string) ($q->storage_package_label ?? ''),
                    ];
                })
                ->form([
                    Textarea::make('storage_package_label')
                        ->label('Подпись для карточки клиента')
                        ->rows(2)
                        ->placeholder('Например: Базовый + 1 ГБ'),
                ])
                ->action(function (array $data): void {
                    $label = trim((string) ($data['storage_package_label'] ?? ''));
                    app(TenantStorageQuotaService::class)->setStoragePackageLabel($this->record, $label !== '' ? $label : null, Auth::id() ? (int) Auth::id() : null);
                    $this->record->refresh();
                    $this->record->load('storageQuota');
                    Notification::make()->title('Подпись сохранена')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function canEditTenantStorage(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyRole(['platform_owner', 'platform_admin']);
    }

    protected function canEditTenantAnalytics(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyRole(['platform_owner', 'platform_admin']);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        if (! $this->canEditTenantAnalytics()) {
            return $data;
        }

        $record = $this->getRecord();
        $persistence = app(AnalyticsSettingsPersistence::class);

        return array_merge(
            $data,
            AnalyticsSettingsFormMapper::toFormState($persistence->load((int) $record->id))
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAnalyticsForm = [];
        foreach (TenantAnalyticsFormSchema::formFieldKeys() as $key) {
            if (array_key_exists($key, $data)) {
                $this->pendingAnalyticsForm[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function afterSave(): void
    {
        if (! $this->canEditTenantAnalytics()) {
            return;
        }

        try {
            $tenantId = (int) $this->record->id;
            $persistence = app(AnalyticsSettingsPersistence::class);
            $before = $persistence->load($tenantId);
            $new = AnalyticsSettingsFormMapper::toValidatedData($this->pendingAnalyticsForm);
            $persistence->save($tenantId, $new, Auth::user(), $before);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                Notification::make()
                    ->title($messages[0] ?? 'Ошибка валидации аналитики')
                    ->danger()
                    ->send();
            }

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }
}
