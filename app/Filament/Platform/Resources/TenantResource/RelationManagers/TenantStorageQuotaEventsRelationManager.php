<?php

namespace App\Filament\Platform\Resources\TenantResource\RelationManagers;

use App\Filament\Tenant\Pages\StorageMonitoringPage;
use App\Models\Tenant;
use App\Models\TenantStorageQuotaEvent;
use App\Tenant\StorageQuota\TenantStorageQuotaStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class TenantStorageQuotaEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'storageQuotaEvents';

    protected static ?string $title = 'Хранилище';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedCircleStack;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        if (! $ownerRecord instanceof Tenant) {
            return null;
        }
        $ownerRecord->loadMissing('storageQuota');
        $q = $ownerRecord->storageQuota;
        if ($q === null) {
            return null;
        }
        $st = (string) ($q->status ?? TenantStorageQuotaStatus::Ok->value);
        if ($st === TenantStorageQuotaStatus::Ok->value || $st === '') {
            return null;
        }

        return '!';
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        if (static::getBadge($ownerRecord, $pageClass) === null) {
            return null;
        }
        $ownerRecord->loadMissing('storageQuota');
        $st = (string) ($ownerRecord->storageQuota?->status ?? '');

        return $st === TenantStorageQuotaStatus::Warning20->value ? 'warning' : 'danger';
    }

    public static function getBadgeTooltip(Model $ownerRecord, string $pageClass): string|Htmlable|null
    {
        if (static::getBadge($ownerRecord, $pageClass) === null) {
            return null;
        }

        return 'Квота хранилища: порог или переполнение.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state): string => StorageMonitoringPage::eventTypeLabel($state)),
                TextColumn::make('usage_display')
                    ->label('Использование')
                    ->getStateUsing(fn (TenantStorageQuotaEvent $record): string => self::formatUsageBytes($record))
                    ->tooltip(fn (TenantStorageQuotaEvent $record): ?string => self::rawPayloadTooltip($record))
                    ->alignEnd(),
                TextColumn::make('objects_display')
                    ->label('Объекты')
                    ->getStateUsing(fn (TenantStorageQuotaEvent $record): string => self::formatObjectCount($record))
                    ->alignEnd(),
                TextColumn::make('status_display')
                    ->label('Статус')
                    ->getStateUsing(fn (TenantStorageQuotaEvent $record): string => self::formatEventStatus($record))
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    private static function formatUsageBytes(TenantStorageQuotaEvent $record): string
    {
        $type = (string) $record->type;
        $p = is_array($record->payload) ? $record->payload : [];

        return match ($type) {
            'recalculated' => isset($p['used_bytes']) && is_numeric($p['used_bytes'])
                ? Number::fileSize((int) $p['used_bytes'], precision: 2)
                : '—',
            'quota_changed' => self::quotaChangedUsageLabel($p),
            'upload_blocked_quota_exceeded' => isset($p['used_bytes']) && is_numeric($p['used_bytes'])
                ? Number::fileSize((int) $p['used_bytes'], precision: 2)
                : '—',
            default => isset($p['free_bytes']) && is_numeric($p['free_bytes'])
                ? Number::fileSize((int) $p['free_bytes'], precision: 2)
                : '—',
        };
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function quotaChangedUsageLabel(array $p): string
    {
        if (isset($p['effective_after']) && is_numeric($p['effective_after'])) {
            return Number::fileSize((int) $p['effective_after'], precision: 2);
        }
        $field = (string) ($p['field'] ?? '');
        if (in_array($field, ['base_quota_bytes', 'extra_quota_bytes'], true)
            && isset($p['after']) && is_numeric($p['after'])) {
            return Number::fileSize((int) $p['after'], precision: 2);
        }

        return '—';
    }

    private static function formatObjectCount(TenantStorageQuotaEvent $record): string
    {
        if ((string) $record->type !== 'recalculated') {
            return '—';
        }
        $p = is_array($record->payload) ? $record->payload : [];
        $summary = $p['summary'] ?? null;
        if (! is_array($summary) || ! array_key_exists('object_count', $summary)) {
            return '—';
        }
        $n = $summary['object_count'];
        if (! is_numeric($n)) {
            return '—';
        }

        return (string) (int) $n;
    }

    private static function formatEventStatus(TenantStorageQuotaEvent $record): string
    {
        $type = (string) $record->type;
        $p = is_array($record->payload) ? $record->payload : [];

        return match ($type) {
            'recalculated' => isset($p['status_after']) && is_string($p['status_after'])
                ? self::quotaStatusLabel($p['status_after'])
                : '—',
            'quota_changed' => self::quotaChangedStatusLabel($p),
            'usage_warning_20', 'usage_critical_10', 'usage_exceeded', 'usage_back_to_normal' => self::transitionStatusLabel($p),
            'upload_blocked_quota_exceeded' => 'Загрузка отклонена',
            default => '—',
        };
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function quotaChangedStatusLabel(array $p): string
    {
        $field = (string) ($p['field'] ?? '');
        if ($field === 'base_quota_bytes') {
            return 'Базовая квота';
        }
        if ($field === 'extra_quota_bytes') {
            return 'Дополнительная квота';
        }
        if ($field === 'storage_package_label') {
            $after = $p['after'] ?? null;

            return 'Пакет: '.(is_string($after) && $after !== '' ? $after : '—');
        }

        return 'Изменение квоты';
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function transitionStatusLabel(array $p): string
    {
        $from = isset($p['from']) && is_string($p['from']) ? self::quotaStatusLabel($p['from']) : '—';
        $to = isset($p['to']) && is_string($p['to']) ? self::quotaStatusLabel($p['to']) : '—';

        return $from.' → '.$to;
    }

    private static function quotaStatusLabel(string $value): string
    {
        $enum = TenantStorageQuotaStatus::tryFrom($value);

        return $enum !== null
            ? StorageMonitoringPage::statusLabel($enum)
            : $value;
    }

    private static function rawPayloadTooltip(TenantStorageQuotaEvent $record): ?string
    {
        $payload = $record->payload;
        if (! is_array($payload) || $payload === []) {
            return null;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (! is_string($json)) {
            return null;
        }

        return Str::limit($json, 4000);
    }
}
