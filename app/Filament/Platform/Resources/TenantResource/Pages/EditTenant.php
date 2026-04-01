<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Shared\TenantAnalyticsFormSchema;
use App\Models\User;
use App\Services\Analytics\AnalyticsSettingsPersistence;
use App\Support\Analytics\AnalyticsSettingsFormMapper;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
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
            Actions\DeleteAction::make(),
        ];
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
