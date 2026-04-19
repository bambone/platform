<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Jobs\RecalculateTenantStorageUsageJob;
use App\Models\PlatformSetting;
use App\Models\TenantStorageQuotaEvent;
use App\Tenant\StorageQuota\TenantStorageQuotaData;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use App\Tenant\StorageQuota\TenantStorageQuotaStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use UnitEnum;

class StorageMonitoringPage extends Page
{
    protected static ?string $navigationLabel = 'Мониторинг и лимиты';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $title = 'Мониторинг и лимиты';

    protected static ?string $slug = 'storage-monitoring';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected string $view = 'filament.tenant.pages.storage-monitoring';

    public static function canAccess(): bool
    {
        return Gate::allows('manage_settings') && \currentTenant() !== null;
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('manage_settings'), 403);
        abort_if(\currentTenant() === null, 404);
    }

    protected function getHeaderActions(): array
    {
        $tenant = \currentTenant();
        if ($tenant === null) {
            return [];
        }

        $actions = [
            TenantPanelHintHeaderAction::makeLines(
                'storageMonitoringWhatIs',
                [
                    'Занятость хранилища тенанта по публичным и приватным файлам, события квот.',
                    '',
                    '«Синхронизировать» пересчитывает фактический объём в бакете.',
                ],
                'Справка по мониторингу хранилища',
            ),
        ];

        if (Gate::allows('manage_settings')) {
            $actions[] = Action::make('syncStorageUsage')
                ->label('Синхронизировать с хранилищем')
                ->icon('heroicon-o-arrow-path')
                ->action(function () use ($tenant): void {
                    abort_unless(Gate::allows('manage_settings'), 403);
                    abort_if(\currentTenant() === null || (int) \currentTenant()->id !== (int) $tenant->id, 403);

                    RecalculateTenantStorageUsageJob::dispatchSync((int) $tenant->id);
                    Notification::make()
                        ->title('Данные обновлены')
                        ->body('Занятое место пересчитано по объектам в хранилище (публичный и приватный диск).')
                        ->success()
                        ->send();
                });
        }

        return $actions;
    }

    #[Computed]
    public function quotaData(): ?TenantStorageQuotaData
    {
        $t = \currentTenant();
        if ($t === null) {
            return null;
        }

        return app(TenantStorageQuotaService::class)->forTenant($t);
    }

    /**
     * @return Collection<int, TenantStorageQuotaEvent>
     */
    #[Computed]
    public function recentEvents()
    {
        $t = \currentTenant();
        if ($t === null) {
            return collect();
        }

        return TenantStorageQuotaEvent::query()
            ->where('tenant_id', $t->id)
            ->latest()
            ->limit(40)
            ->get();
    }

    public function expansionHint(): string
    {
        $raw = PlatformSetting::get('tenant_storage.tenant_expansion_hint', '');

        return is_string($raw) && trim($raw) !== ''
            ? trim($raw)
            : 'Чтобы расширить лимит хранилища, обратитесь к администратору платформы.';
    }

    public function supportMailto(): ?string
    {
        $email = PlatformSetting::get('platform_support_email', '');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? 'mailto:'.rawurlencode($email).'?subject='.rawurlencode('Расширение хранилища')
            : null;
    }

    public static function statusLabel(TenantStorageQuotaStatus $s): string
    {
        return match ($s) {
            TenantStorageQuotaStatus::Ok => 'Норма',
            TenantStorageQuotaStatus::Warning20 => 'Заканчивается место',
            TenantStorageQuotaStatus::Critical10 => 'Критически мало места',
            TenantStorageQuotaStatus::Exceeded => 'Лимит превышен',
        };
    }

    public static function eventTypeLabel(string $type): string
    {
        return match ($type) {
            'quota_changed' => 'Изменение квоты',
            'usage_warning_20' => 'Порог 20% остатка',
            'usage_critical_10' => 'Порог 10% остатка',
            'usage_exceeded' => 'Лимит исчерпан',
            'usage_back_to_normal' => 'Вернулось в норму',
            'upload_blocked_quota_exceeded' => 'Загрузка отклонена (квота)',
            'recalculated' => 'Пересчёт использования',
            default => $type,
        };
    }
}
