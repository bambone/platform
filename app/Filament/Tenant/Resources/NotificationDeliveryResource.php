<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\NotificationDeliveryResource\Pages;
use App\Models\NotificationDelivery;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class NotificationDeliveryResource extends Resource
{
    protected static ?string $model = NotificationDelivery::class;

    protected static ?string $panel = 'admin';

    protected static ?string $navigationLabel = 'История доставок';

    protected static ?string $pluralModelLabel = 'Доставки';

    protected static ?string $modelLabel = 'Доставка';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 27;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    public static function canAccess(): bool
    {
        return Gate::allows('view_notification_history') || Gate::allows('manage_notifications');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = currentTenant();
        $query = parent::getEloquentQuery();
        if ($tenant === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('tenant_id', $tenant->id)
            ->with(['event']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('event.event_key')->label('Событие'),
                TextColumn::make('channel_type')->label('Канал')->badge(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('created_at')->label('Создано')->dateTime(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationDeliveries::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
