<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages;
use App\Filament\Tenant\Support\TenantMoneyForms;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\MediaPresentation\PresentationData;
use App\Models\TenantServiceProgram;
use App\Support\Storage\TenantPublicAssetResolver;
use App\Money\MoneyBindingRegistry;
use App\Tenant\Expert\ServiceProgramType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
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
                            ->label('URL-идентификатор')
                            ->required()
                            ->maxLength(128)
                            ->helperText('Короткий адрес в ссылке, без пробелов. Уникален внутри клиента.'),
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
                        TenantMoneyForms::moneyTextInput('price_amount', MoneyBindingRegistry::TENANT_SERVICE_PROGRAM_PRICE_AMOUNT, 'Цена', required: false, nullableStorage: true)
                            ->helperText('Ввод в человекочитаемом виде по настройкам «Деньги / Валюта». Оставьте пустым для «По запросу».'),
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
                Section::make('Обложка карточки программы')
                    ->description('На сайте сверху карточки показывается широкий баннер. Для компьютера загрузите горизонтальное изображение (~1200×640, WebP). Для телефона можно отдельно выбрать вертикальное (~720×1040); если не загрузить — на узком экране подставится баннер для компьютера.')
                    ->schema([
                        TenantPublicImagePicker::make('cover_image_ref')
                            ->label('Баннер для компьютера (широкий)')
                            ->uploadPublicSiteSubdirectory(fn (Get $get): string => 'expert_auto/programs/'.trim((string) ($get('slug') ?: 'draft')))
                            ->helperText('Рекомендуемый размер около 1200×640, формат WebP. Файл сохранится в медиатеке вместе с этой программой.')
                            ->live()
                            ->columnSpanFull(),
                        TenantPublicImagePicker::make('cover_mobile_ref')
                            ->label('Баннер для телефона (портрет, по желанию)')
                            ->uploadPublicSiteSubdirectory(fn (Get $get): string => 'expert_auto/programs/'.trim((string) ($get('slug') ?: 'draft')))
                            ->helperText('Рекомендуемый размер около 720×1040. Если не загрузить — на телефоне используется баннер для компьютера.')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('cover_image_alt')
                            ->label('Alt-текст для изображения')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Hidden::make('cover_presentation.version')
                            ->default(PresentationData::CURRENT_VERSION)
                            ->dehydrated(),
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                Section::make('Фокус (mobile / узкий экран)')
                                    ->description('До 1023px; при отдельном файле для телефона превью покажет его.')
                                    ->schema([
                                        TextInput::make('cover_presentation.viewport_focal_map.mobile.x')
                                            ->label('X %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.1)
                                            ->required()
                                            ->live(onBlur: true),
                                        TextInput::make('cover_presentation.viewport_focal_map.mobile.y')
                                            ->label('Y %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.1)
                                            ->required()
                                            ->live(onBlur: true),
                                    ])->columns(2),
                                Section::make('Фокус (desktop)')
                                    ->description('От 1024px')
                                    ->schema([
                                        TextInput::make('cover_presentation.viewport_focal_map.desktop.x')
                                            ->label('X %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.1)
                                            ->required()
                                            ->live(onBlur: true),
                                        TextInput::make('cover_presentation.viewport_focal_map.desktop.y')
                                            ->label('Y %')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.1)
                                            ->required()
                                            ->live(onBlur: true),
                                    ])->columns(2),
                            ])
                            ->columnSpanFull(),
                        ViewField::make('cover_presentation_preview')
                            ->hiddenLabel()
                            ->view('filament.forms.components.service-program-cover-preview')
                            ->viewData(function (Get $get): array {
                                $t = currentTenant();
                                $frames = ServiceProgramCardPresentationProfile::previewFrames();
                                $safeArea = ServiceProgramCardPresentationProfile::safeAreaBottomBand();
                                $cover = $get('cover_presentation') ?? [];
                                $map = is_array($cover['viewport_focal_map'] ?? null) ? $cover['viewport_focal_map'] : [];
                                $mx = (float) ($map['mobile']['x'] ?? 50);
                                $my = (float) ($map['mobile']['y'] ?? 52);
                                $dx = (float) ($map['desktop']['x'] ?? 50);
                                $dy = (float) ($map['desktop']['y'] ?? 48);
                                $tenantId = $t ? (int) $t->id : 0;
                                $desktopUrl = $tenantId !== 0
                                    ? TenantPublicAssetResolver::resolve(trim((string) ($get('cover_image_ref') ?? '')), $tenantId)
                                    : null;
                                $mobileUrl = $tenantId !== 0
                                    ? TenantPublicAssetResolver::resolve(trim((string) ($get('cover_mobile_ref') ?? '')), $tenantId)
                                    : null;
                                if (($mobileUrl === null || $mobileUrl === '') && $desktopUrl) {
                                    $mobileUrl = $desktopUrl;
                                }

                                $tiles = [];
                                foreach ($frames as $frame) {
                                    $key = (string) ($frame['key'] ?? '');
                                    $isDesktop = $key === 'desktop';
                                    $fx = $isDesktop ? $dx : $mx;
                                    $fy = $isDesktop ? $dy : $my;
                                    $src = $isDesktop ? $desktopUrl : $mobileUrl;
                                    $tiles[] = [
                                        'label' => (string) ($frame['label'] ?? $key),
                                        'width' => (int) ($frame['width'] ?? 200),
                                        'height' => (int) ($frame['height'] ?? 120),
                                        'fx' => $fx,
                                        'fy' => $fy,
                                        'src' => $src,
                                    ];
                                }

                                return [
                                    'tiles' => $tiles,
                                    'safeArea' => $safeArea,
                                ];
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('tenant'))
            ->columns([
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('slug'),
                TextColumn::make('program_type'),
                IconColumn::make('is_featured')->boolean(),
                IconColumn::make('is_visible')->boolean(),
                TextColumn::make('price_amount')
                    ->label('Цена')
                    ->formatStateUsing(function ($state, TenantServiceProgram $record): string {
                        if ($state === null) {
                            return '—';
                        }
                        $t = $record->tenant ?? currentTenant();
                        if ($t === null) {
                            return (string) $state;
                        }

                        return tenant_money_format((int) $state, MoneyBindingRegistry::TENANT_SERVICE_PROGRAM_PRICE_AMOUNT, $t);
                    })
                    ->sortable(),
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
