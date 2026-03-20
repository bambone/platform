<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Tenant\Resources\MotorcycleResource\Pages;
use App\Models\Motorcycle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class MotorcycleResource extends Resource
{
    protected static ?string $model = Motorcycle::class;

    protected static ?string $navigationLabel = 'Мотоциклы';

    protected static ?string $modelLabel = 'Мотоцикл';

    protected static ?string $pluralModelLabel = 'Мотоциклы';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 12])
            ->components([
                // Левая колонка 8/12 — основной контент
                Section::make()
                    ->schema([
                        Section::make('Основная информация')
                            ->description('Название, идентификатор и краткое описание карточки')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Название')
                                    ->id('motorcycle-name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                                        if ($operation === 'create' && $state) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->label('URL (slug)')
                                    ->id('motorcycle-slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Уникальный идентификатор в URL'),
                                TextInput::make('brand')
                                    ->label('Бренд')
                                    ->id('motorcycle-brand')
                                    ->maxLength(255),
                                TextInput::make('model')
                                    ->label('Модель')
                                    ->id('motorcycle-model')
                                    ->maxLength(255),
                                Textarea::make('short_description')
                                    ->label('Краткое описание')
                                    ->id('motorcycle-short-description')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Полное описание')
                            ->description('Подробное описание для карточки мотоцикла')
                            ->schema([
                                RichEditor::make('full_description')
                                    ->label('Описание')
                                    ->id('motorcycle-full-description')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Характеристики')
                            ->description('Технические параметры мотоцикла')
                            ->schema([
                                Section::make('Базовые параметры')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('engine_cc')
                                                    ->label('Объём двигателя')
                                                    ->id('motorcycle-engine-cc')
                                                    ->numeric()
                                                    ->suffix('см³'),
                                                TextInput::make('power')
                                                    ->label('Мощность')
                                                    ->id('motorcycle-power')
                                                    ->numeric()
                                                    ->suffix('л.с.'),
                                                TextInput::make('transmission')
                                                    ->label('Трансмиссия')
                                                    ->id('motorcycle-transmission')
                                                    ->maxLength(255),
                                                TextInput::make('year')
                                                    ->label('Год выпуска')
                                                    ->id('motorcycle-year')
                                                    ->numeric(),
                                                TextInput::make('mileage')
                                                    ->label('Пробег')
                                                    ->id('motorcycle-mileage')
                                                    ->numeric()
                                                    ->suffix('км'),
                                            ]),
                                    ])
                                    ->columns(1)
                                    ->compact()
                                    ->secondary(),
                                Section::make('Дополнительные параметры')
                                    ->description('Вес, высота седла, расход и т.д.')
                                    ->schema([
                                        KeyValue::make('specs_json')
                                            ->label('')
                                            ->id('motorcycle-specs-json')
                                            ->keyLabel('Параметр')
                                            ->valueLabel('Значение')
                                            ->reorderable(),
                                    ])
                                    ->columns(1)
                                    ->compact()
                                    ->secondary(),
                            ])
                            ->columns(1),

                        SeoMetaFields::make(useTabs: true),
                    ])
                    ->columnSpan(['default' => 12, 'lg' => 8]),

                // Правая колонка 4/12 — единый sidebar модуль
                Section::make('Управление карточкой')
                    ->description('Публикация, цены и медиа')
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->id('motorcycle-status')
                            ->options(Motorcycle::statuses())
                            ->required()
                            ->default('available'),
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->id('motorcycle-sort-order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('show_on_home')
                            ->label('Показывать на главной')
                            ->id('motorcycle-show-on-home')
                            ->default(false),
                        Toggle::make('show_in_catalog')
                            ->label('Показывать в каталоге')
                            ->id('motorcycle-show-in-catalog')
                            ->default(true),
                        Toggle::make('is_recommended')
                            ->label('Рекомендуемый')
                            ->id('motorcycle-is-recommended')
                            ->default(false),
                        Select::make('category_id')
                            ->label('Категория')
                            ->id('motorcycle-category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('price_per_day')
                            ->label('Цена за день')
                            ->id('motorcycle-price-per-day')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->suffix('₽'),
                        TextInput::make('price_2_3_days')
                            ->label('2–3 дня')
                            ->id('motorcycle-price-2-3-days')
                            ->numeric()
                            ->suffix('₽'),
                        TextInput::make('price_week')
                            ->label('Неделя')
                            ->id('motorcycle-price-week')
                            ->numeric()
                            ->suffix('₽'),
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->image()
                            ->label('Обложка')
                            ->helperText('Основное изображение карточки. Рекомендуется 16:9.')
                            ->id('motorcycle-cover')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->image()
                            ->multiple()
                            ->maxFiles(10)
                            ->reorderable()
                            ->label('Галерея')
                            ->helperText('Дополнительные изображения для слайдера.')
                            ->id('motorcycle-gallery')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpan(['default' => 12, 'lg' => 4]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->sortable(),
                TextColumn::make('price_per_day')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Motorcycle::statuses()[$state] ?? $state),
                IconColumn::make('show_on_home')
                    ->boolean(),
                IconColumn::make('show_in_catalog')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Motorcycle::statuses()),
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMotorcycles::route('/'),
            'create' => Pages\CreateMotorcycle::route('/create'),
            'edit' => Pages\EditMotorcycle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
