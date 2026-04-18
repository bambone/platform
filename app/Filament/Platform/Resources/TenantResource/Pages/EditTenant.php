<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Shared\TenantAnalyticsFormSchema;
use App\Filament\Support\TenantPushPlatformFormSchema;
use App\Jobs\RecalculateTenantStorageUsageJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Analytics\AnalyticsSettingsPersistence;
use App\Services\TenantPush\TenantPushPlatformOwnedSettingsService;
use App\Support\Analytics\AnalyticsSettingsFormMapper;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushOverride;
use App\Support\TenantRegionalContract;
use App\Support\TenantSlug;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Size;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Filament v5: combined tabs — порядок вкладок задаётся {@see TenantResource::getRelations()}.
     * Состояние формы в $this->data на странице; при смене вкладки рендерится только активная панель.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Клиент';
    }

    public function getContentTabIcon(): string|\BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedBuildingOffice2;
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }

    public function getHeading(): string|Htmlable|null
    {
        $record = $this->getRecord();
        if (! $record instanceof Tenant) {
            return parent::getHeading();
        }

        $status = (string) ($record->status ?? '');
        $label = Tenant::statuses()[$status] ?? ($status !== '' ? $status : '—');
        $colorClass = match ($status) {
            'active' => 'fi-color-success',
            'trial' => 'fi-color-warning',
            'suspended' => 'fi-color-danger',
            'archived' => 'fi-color-gray',
            default => 'fi-color-gray',
        };

        $name = e($record->name);
        $badgeLabel = e(is_string($label) ? $label : (string) $label);
        $html = '<span class="inline-flex max-w-full flex-wrap items-center gap-x-2 gap-y-1 align-middle">'
            .'<span class="text-xl font-semibold leading-tight tracking-tight text-gray-950 dark:text-white">'.$name.'</span>'
            .'<span class="fi-badge fi-color '.$colorClass.' fi-size-sm shrink-0 self-center leading-none"><span class="fi-badge-label-ctn"><span class="fi-badge-label">'.$badgeLabel.'</span></span></span>'
            .'</span>';

        return new HtmlString($html);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();
        if (! $record instanceof Tenant) {
            return null;
        }

        $record->loadMissing('domains');
        $primary = $record->domains->firstWhere('is_primary', true) ?? $record->domains->first();
        $host = $primary && filled($primary->host) ? strtolower(trim((string) $primary->host)) : null;
        $cabinet = $record->cabinetAdminUrl();

        $chunks = [];
        if ($host !== null && $host !== '') {
            $siteUrl = 'https://'.$host;
            $chunks[] = 'Домен: <a class="fi-link text-primary-600 underline" href="'.e($siteUrl).'" target="_blank" rel="noopener noreferrer">'.e($host).'</a>';
        } else {
            $chunks[] = 'Домен не подключён';
        }
        if ($cabinet !== null) {
            $cabinetHost = parse_url($cabinet, PHP_URL_HOST);
            $label = is_string($cabinetHost) && $cabinetHost !== '' ? $cabinetHost.'/admin' : $cabinet;
            $chunks[] = 'Кабинет: <a class="fi-link text-primary-600 underline" href="'.e($cabinet).'" target="_blank" rel="noopener noreferrer">'.e($label).'</a>';
        }

        return new HtmlString(implode(' · ', $chunks));
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->size(Size::Large)
            ->extraAttributes(['class' => 'fi-btn-tenant-edit-save']);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        DB::transaction(function () use ($shouldRedirect, $shouldSendSavedNotification): void {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        });
    }

    /**
     * @var array<string, mixed>
     */
    protected array $pendingAnalyticsForm = [];

    /**
     * @var array<string, mixed>
     */
    protected array $pendingPushSettingsForm = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculateTenantStorage')
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
            Action::make('editExtraStorageQuota')
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
            Action::make('editStoragePackageLabel')
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

        $record = $this->getRecord();
        if ($record instanceof Tenant) {
            $push = app(TenantPushFeatureGate::class)->findSettings($record);
            $data['platform_push_override'] = $push?->push_override ?? TenantPushOverride::InheritPlan->value;
            $data['platform_push_commercial_active'] = $push?->commercial_service_active ?? false;
            $data['platform_push_self_serve_allowed'] = $push?->self_serve_allowed ?? true;
        }

        if (! $this->canEditTenantAnalytics()) {
            return $data;
        }

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
        if (isset($data['slug']) && is_string($data['slug'])) {
            $data['slug'] = TenantSlug::normalize($data['slug']);
            if (TenantSlug::isNormalizedSlugTaken($data['slug'], (int) $this->record->getKey())) {
                throw ValidationException::withMessages([
                    'slug' => 'Такой URL-идентификатор уже занят (после нормализации он совпадает с существующим клиентом).',
                ]);
            }
        }
        if (isset($data['locale']) && is_string($data['locale'])) {
            $loc = TenantRegionalContract::normalizeLocale($data['locale']);
            if ($loc === null || ! TenantRegionalContract::isValidLocale($loc)) {
                throw ValidationException::withMessages([
                    'locale' => 'Укажите корректную локаль (например ru или en-US).',
                ]);
            }
            $data['locale'] = $loc;
        }
        if (isset($data['currency']) && is_string($data['currency'])) {
            $cur = TenantRegionalContract::normalizeCurrency($data['currency']);
            if ($cur === null || ! TenantRegionalContract::isValidCurrency($cur)) {
                throw ValidationException::withMessages([
                    'currency' => 'Укажите трёхбуквенный код валюты ISO 4217 (например RUB).',
                ]);
            }
            $data['currency'] = $cur;
        }
        if (isset($data['country']) && is_string($data['country'])) {
            $data['country'] = TenantRegionalContract::normalizeCountry($data['country']);
            if (! TenantRegionalContract::isValidCountryOrEmpty($data['country'])) {
                throw ValidationException::withMessages([
                    'country' => 'Страна: двухбуквенный код ISO 3166-1 (например RU) или оставьте пустым.',
                ]);
            }
        }

        $this->pendingPushSettingsForm = [];
        foreach (TenantPushPlatformFormSchema::formFieldKeys() as $key) {
            if (array_key_exists($key, $data)) {
                $this->pendingPushSettingsForm[$key] = $data[$key];
                unset($data[$key]);
            }
        }

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
        $this->persistPushPlatformSettings();

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

    private function persistPushPlatformSettings(): void
    {
        if ($this->pendingPushSettingsForm === []) {
            return;
        }

        $tenant = $this->record;
        if (! $tenant instanceof Tenant) {
            return;
        }

        app(TenantPushPlatformOwnedSettingsService::class)->applyFromFormData(
            $tenant,
            $this->pendingPushSettingsForm,
            Auth::user(),
        );
    }
}
