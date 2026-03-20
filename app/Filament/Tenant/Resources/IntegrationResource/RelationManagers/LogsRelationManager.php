<?php

namespace App\Filament\Tenant\Resources\IntegrationResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Логи';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('action')->badge(),
                TextColumn::make('status')->badge()->color(fn (?string $state): string => match ($state ?? '') {
                    'success' => 'success',
                    'error' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('error_message')->limit(50)->placeholder('—'),
                TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
