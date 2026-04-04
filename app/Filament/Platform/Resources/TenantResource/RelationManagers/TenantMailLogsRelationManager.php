<?php

namespace App\Filament\Platform\Resources\TenantResource\RelationManagers;

use App\Filament\Platform\Resources\TenantMailLogResource;
use App\Models\TenantMailLog;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TenantMailLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'mailLogs';

    protected static ?string $title = 'Почта';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedEnvelope;

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('correlation_id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('to_email')
                    ->label('Кому')
                    ->searchable(),
                TextColumn::make('mail_type')
                    ->label('Тип')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('subject')
                    ->label('Тема')
                    ->limit(40)
                    ->tooltip(fn (TenantMailLog $record): ?string => $record->subject),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (TenantMailLog::statusLabels()[$state] ?? $state) : '—')
                    ->color(fn (?string $state): string => match ($state) {
                        TenantMailLog::STATUS_SENT => 'success',
                        TenantMailLog::STATUS_FAILED => 'danger',
                        TenantMailLog::STATUS_DEFERRED => 'warning',
                        TenantMailLog::STATUS_QUEUED => 'gray',
                        TenantMailLog::STATUS_PROCESSING => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('attempts')
                    ->label('Попытки')
                    ->sortable(),
                TextColumn::make('throttled_count')
                    ->label('Throttled')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(TenantMailLog::statusLabels()),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (TenantMailLog $record): string => TenantMailLogResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Писем пока нет')
            ->emptyStateDescription('Когда платформа отправит письма этому клиенту (уведомления, приглашения и т.п.), они появятся здесь с датой, темой и статусом доставки.')
            ->emptyStateIcon(Heroicon::OutlinedEnvelope);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
