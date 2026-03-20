<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\LeadResource\Pages;
use App\Models\Lead;
use Filament\Actions\EditAction;
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
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('phone')->required()->tel()->maxLength(20),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('messenger')->maxLength(255),
                    ])->columns(2),

                Section::make('Детали заявки')
                    ->schema([
                        Select::make('motorcycle_id')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('rental_date_from')->date(),
                        TextInput::make('rental_date_to')->date(),
                        Select::make('source')->options(Lead::sources()),
                        Textarea::make('comment')->rows(3),
                    ])->columns(2),

                Section::make('Управление')
                    ->schema([
                        Select::make('status')
                            ->options(Lead::statuses())
                            ->required()
                            ->live(),
                        Select::make('assigned_user_id')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload(),
                        Textarea::make('manager_notes')->rows(4),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('motorcycle.name')->sortable()->placeholder('—'),
                TextColumn::make('rental_date_from')->date('d.m.Y')->sortable()->placeholder('—'),
                TextColumn::make('rental_date_to')->date('d.m.Y')->placeholder('—'),
                TextColumn::make('source')
                    ->formatStateUsing(fn (?string $state): string => Lead::sources()[$state] ?? $state ?? '—')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Lead::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'in_progress' => 'warning',
                        'confirmed', 'completed' => 'success',
                        'cancelled', 'spam' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Lead::statuses()),
                SelectFilter::make('source')->options(Lead::sources()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
            ]);
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
