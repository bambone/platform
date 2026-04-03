<?php

namespace App\Filament\Platform\Resources\TenantResource\RelationManagers;

use App\Filament\Tenant\Pages\StorageMonitoringPage;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantStorageQuotaEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'storageQuotaEvents';

    protected static ?string $title = 'События квоты хранилища';

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
                TextColumn::make('payload')
                    ->label('Данные')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state) || $state === []) {
                            return '—';
                        }

                        return Str::limit(json_encode($state, JSON_UNESCAPED_UNICODE), 200);
                    })
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
