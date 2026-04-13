<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PlatformActivityWidget extends BaseWidget
{
    /** См. PlatformDashboardIntroWidget: синхронный рендер виджета таблицы. */
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Недавняя активность (Новые клиенты)';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Время')
                    ->dateTime('d.m.Y H:i')
                    ->description(fn (Tenant $record) => $record->created_at?->diffForHumans()),
                Tables\Columns\TextColumn::make('name')
                    ->label('Клиент')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'suspended', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('domains_count')
                    ->counts('domains')
                    ->label('Домены')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'warning'),
            ])
            ->paginated(false);
    }
}
