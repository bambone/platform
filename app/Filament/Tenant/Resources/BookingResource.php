<?php

namespace App\Filament\Tenant\Resources;

use App\Enums\BookingStatus;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Support\FilamentMotorcycleThumbnail;
use App\Terminology\DomainTermKeys;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BookingResource extends Resource
{
    use ResolvesDomainTermLabels;

    protected static ?string $model = Booking::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 30;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function getNavigationLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::BOOKING_PLURAL, 'Бронирования');
    }

    public static function getModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::BOOKING, 'Бронирование');
    }

    public static function getPluralModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::BOOKING_PLURAL, 'Бронирования');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['motorcycle.media']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Сводка')
                    ->schema([
                        TextEntry::make('booking_number')
                            ->label('Номер'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->formatStateUsing(fn (BookingStatus $state): string => self::statusLabel($state)),
                        TextEntry::make('customer_name')
                            ->label('Клиент'),
                        TextEntry::make('phone')
                            ->label('Телефон'),
                        TextEntry::make('motorcycle.name')
                            ->label('Модель в каталоге')
                            ->placeholder('—'),
                        TextEntry::make('start_date')
                            ->label('Начало')
                            ->date('d.m.Y'),
                        TextEntry::make('end_date')
                            ->label('Окончание')
                            ->date('d.m.Y'),
                        TextEntry::make('total_price')
                            ->label('Сумма')
                            ->money('RUB'),
                        TextEntry::make('created_at')
                            ->label('Создано')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Booking $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('motorcycle_thumb')
                    ->label('')
                    ->getStateUsing(fn (Booking $record): string => FilamentMotorcycleThumbnail::coverUrlOrPlaceholder($record->motorcycle))
                    ->defaultImageUrl(FilamentMotorcycleThumbnail::placeholderDataUrl())
                    ->checkFileExistence(false)
                    ->imageSize(48)
                    ->square()
                    ->extraImgAttributes([
                        'class' => 'rounded-lg object-cover',
                        'loading' => 'lazy',
                        'decoding' => 'async',
                    ])
                    ->extraCellAttributes(['class' => 'w-px pe-0']),
                TextColumn::make('booking_number')
                    ->label('Номер')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('motorcycle.name')
                    ->label('Модель')
                    ->placeholder('—')
                    ->description(fn (Booking $record): string => $record->customer_name ?? '—')
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label('С')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('По')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => self::statusLabel($state))
                    ->color(fn (BookingStatus $state): string => match ($state) {
                        BookingStatus::CONFIRMED, BookingStatus::COMPLETED => 'success',
                        BookingStatus::PENDING, BookingStatus::AWAITING_PAYMENT => 'warning',
                        BookingStatus::CANCELLED, BookingStatus::NO_SHOW => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_price')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('Бронирований пока нет')
            ->emptyStateDescription('Когда клиенты оформят бронь через сайт, записи появятся здесь.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function statusLabel(BookingStatus $state): string
    {
        return match ($state) {
            BookingStatus::DRAFT => 'Черновик',
            BookingStatus::PENDING => 'Ожидает',
            BookingStatus::AWAITING_PAYMENT => 'Ожидает оплаты',
            BookingStatus::CONFIRMED => 'Подтверждено',
            BookingStatus::CANCELLED => 'Отменено',
            BookingStatus::COMPLETED => 'Завершено',
            BookingStatus::NO_SHOW => 'Неявка',
        };
    }
}
