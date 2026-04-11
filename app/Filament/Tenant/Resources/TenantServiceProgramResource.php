<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages;
use App\Models\TenantServiceProgram;
use App\Tenant\Expert\ServiceProgramType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TenantServiceProgramResource extends Resource
{
    protected static ?string $model = TenantServiceProgram::class;

    protected static ?string $navigationLabel = 'Программы';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 15;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $modelLabel = 'Программа';

    protected static ?string $pluralModelLabel = 'Программы';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->description('Оформление главной и других страниц — в разделе «Страницы» (конструктор секций).')
                    ->schema([
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(128)
                            ->helperText('Уникален внутри клиента.'),
                        TextInput::make('title')->label('Название')->required()->maxLength(255),
                        Textarea::make('teaser')->label('Короткий лид')->rows(2)->columnSpanFull(),
                        Textarea::make('description')->label('Описание')->rows(4)->columnSpanFull(),
                        Select::make('program_type')
                            ->label('Тип')
                            ->options(collect(ServiceProgramType::cases())->mapWithKeys(
                                fn (ServiceProgramType $t): array => [$t->value => $t->label()]
                            ))
                            ->required(),
                        TextInput::make('duration_label')->label('Длительность (текстом)')->maxLength(255),
                        TextInput::make('format_label')->label('Формат занятия')->maxLength(255),
                        TextInput::make('price_amount')
                            ->label('Цена (копейки)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Целое число в минимальных единицах валюты (для RUB — копейки). Оставьте пустым для «По запросу».'),
                        TextInput::make('price_prefix')->label('Префикс цены («от» и т.п.)')->maxLength(32),
                        Toggle::make('is_featured')->label('Избранное (широкая карточка)'),
                        Toggle::make('is_visible')->label('Видимость на сайте')->default(true),
                        TextInput::make('sort_order')->numeric()->default(0),
                    ])->columns(2),
                Section::make('Тексты на карточке программы')
                    ->schema([
                        Repeater::make('audience_json')
                            ->label('Кому подходит')
                            ->schema([
                                TextInput::make('text')
                                    ->label('Пункт')
                                    ->maxLength(500)
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Добавить пункт')
                            ->reorderable()
                            ->columnSpanFull(),
                        Repeater::make('outcomes_json')
                            ->label('Результат / что даёт программа')
                            ->schema([
                                TextInput::make('text')
                                    ->label('Пункт')
                                    ->maxLength(500)
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Добавить пункт')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])->columns(1),
                Section::make('Обложка карточки программы (R2)')
                    ->description('Рекомендуемый путь: expert_auto/programs/{slug}/card-cover-desktop.webp и card-cover-mobile.webp (публичный диск тенанта).')
                    ->schema([
                        TenantPublicImagePicker::make('cover_image_ref')
                            ->label('Desktop (портретная панель, ~1200×1500)')
                            ->uploadPublicSiteSubdirectory(fn (Get $get): string => 'expert_auto/programs/'.trim((string) ($get('slug') ?: 'draft')))
                            ->helperText('Ключ вида tenants/{id}/public/expert_auto/programs/{slug}/card-cover-desktop.webp или относительный путь под public.')
                            ->columnSpanFull(),
                        TenantPublicImagePicker::make('cover_mobile_ref')
                            ->label('Mobile (широкий crop, ~1280×720), опционально')
                            ->uploadPublicSiteSubdirectory(fn (Get $get): string => 'expert_auto/programs/'.trim((string) ($get('slug') ?: 'draft')))
                            ->helperText('Если пусто — на узких экранах подставится desktop-версия.')
                            ->columnSpanFull(),
                        TextInput::make('cover_image_alt')
                            ->label('Alt-текст для изображения')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('cover_object_position')
                            ->label('Object position (CSS)')
                            ->maxLength(64)
                            ->placeholder('напр. center 30%')
                            ->helperText('Только для тонкой подгонки кадра; оставьте пустым для center.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('slug'),
                TextColumn::make('program_type'),
                IconColumn::make('is_featured')->boolean(),
                IconColumn::make('is_visible')->boolean(),
                TextColumn::make('price_amount')->label('Коп.')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantServicePrograms::route('/'),
            'create' => Pages\CreateTenantServiceProgram::route('/create'),
            'edit' => Pages\EditTenantServiceProgram::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return currentTenant()?->themeKey() === 'expert_auto';
    }
}
