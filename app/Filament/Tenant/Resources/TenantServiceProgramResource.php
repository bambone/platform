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
use App\Filament\Tenant\Resources\Resource;
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

    /** В «Каталоге» рядом с прежним местом «курсов» (Motorcycle), скрытым для expert_auto. */
    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $modelLabel = 'Программа';

    protected static ?string $pluralModelLabel = 'Программы';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->description('Данные карточек блока «Программы обучения» на сайте. Состав страниц и порядок секций — в «Страницы» (конструктор).')
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
                    ->description('В витрине: широкий баннер сверху карточки. Desktop — горизонтальный кадр (~1200×640, WebP); mobile — отдельный вертикальный (~720×1040). Для темы expert_auto массовая заливка пресетов из системного пула: php artisan tenant:sync-program-cover-bundle {slug} (пресеты в R2: tenants/_system/themes/expert_auto/program-covers/, см. expert:seed-system-program-covers / theme:push-system-bundled).')
                    ->schema([
                        TenantPublicImagePicker::make('cover_image_ref')
                            ->label('Desktop (широкий баннер)')
                            ->uploadPublicSiteSubdirectory(fn (Get $get): string => 'expert_auto/programs/'.trim((string) ($get('slug') ?: 'draft')))
                            ->helperText('Рекомендуемо ~1200×640 (WebP). Путь: site/expert_auto/programs/{slug}/card-cover-desktop.webp')
                            ->columnSpanFull(),
                        TenantPublicImagePicker::make('cover_mobile_ref')
                            ->label('Mobile (портрет, опционально)')
                            ->uploadPublicSiteSubdirectory(fn (Get $get): string => 'expert_auto/programs/'.trim((string) ($get('slug') ?: 'draft')))
                            ->helperText('Рекомендуемо ~720×1040 под узкий экран. Если пусто — используется desktop.')
                            ->columnSpanFull(),
                        TextInput::make('cover_image_alt')
                            ->label('Alt-текст для изображения')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Select::make('cover_object_position_preset')
                            ->label('Фокус кадра на баннере')
                            ->helperText('Вертикаль обрезки (object-position). «Авто» ≈ center 18%. Обложки из site/brand/ после смены кропа в коде — снова tenant:sync-program-cover-bundle.')
                            ->options([
                                'auto' => 'Авто (рекомендуется)',
                                'center top' => 'Верх кадра',
                                'center 22%' => 'Сильно вверх (лица)',
                                'center 30%' => 'Чуть выше центра',
                                'center center' => 'Ровно по центру',
                                'center 72%' => 'Чуть ниже центра',
                                'center bottom' => 'Низ кадра',
                                '__other__' => 'Свой CSS…',
                            ])
                            ->default('auto')
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('cover_object_position')
                            ->label('Свой object-position')
                            ->maxLength(64)
                            ->placeholder('напр. center 18% или 50% 20%')
                            ->visible(fn (Get $get): bool => $get('cover_object_position_preset') === '__other__')
                            ->required(fn (Get $get): bool => $get('cover_object_position_preset') === '__other__')
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
