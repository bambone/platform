<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\LeadResource\Pages;
use App\Models\Lead;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationLabel = 'Заявки';

    protected static ?string $modelLabel = 'Заявка';

    protected static ?string $pluralModelLabel = 'Заявки';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Контактные данные')
                    ->description('Как с вами связался потенциальный клиент.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->required()
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('messenger')
                            ->label('Мессенджер')
                            ->maxLength(255)
                            ->helperText('Ник или способ связи, если указан.'),
                    ])->columns(2),

                Section::make('Детали заявки')
                    ->description('Интерес к технике и даты; помогает менеджеру подготовить ответ.')
                    ->schema([
                        Select::make('motorcycle_id')
                            ->label('Интерес к карточке каталога')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Можно оставить пустым, если заявка общая.'),
                        DatePicker::make('rental_date_from')
                            ->label('Дата начала аренды')
                            ->native(false),
                        DatePicker::make('rental_date_to')
                            ->label('Дата окончания аренды')
                            ->native(false),
                        Select::make('source')
                            ->label('Источник')
                            ->options(Lead::sources())
                            ->helperText('Откуда пришла заявка: форма на сайте, звонок и т.д.'),
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3),
                    ])->columns(2),

                Section::make('Работа менеджера')
                    ->description('Видно только в кабинете; на сайт не выводится.')
                    ->schema([
                        Select::make('status')
                            ->label('Статус заявки')
                            ->options(Lead::statuses())
                            ->required()
                            ->live()
                            ->helperText('Новая — ещё не обработана. В работе — менеджер связался. Завершена/отменена фиксируют исход.'),
                        Select::make('assigned_user_id')
                            ->label('Ответственный')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Кто ведёт заявку внутри команды.'),
                        Textarea::make('manager_notes')
                            ->label('Внутренние заметки')
                            ->rows(4)
                            ->helperText('Не показываются клиенту.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Получена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('motorcycle.name')
                    ->label('Каталог')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('rental_date_from')
                    ->label('С')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('rental_date_to')
                    ->label('По')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn (?string $state): string => Lead::sources()[$state] ?? $state ?? '—')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Lead::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'in_progress' => 'warning',
                        'confirmed', 'completed' => 'success',
                        'cancelled', 'spam' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Lead::statuses()),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options(Lead::sources()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Заявок пока нет')
            ->emptyStateDescription('Когда посетители отправят форму на сайте, заявки появятся здесь.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'new')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
