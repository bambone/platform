<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Tenant\Resources\TenantServiceProgramResource\Pages;
use App\Filament\Tenant\Support\ServiceProgramCoverPreviewViewDataFactory;
use App\Filament\Tenant\Support\TenantMoneyForms;
use App\MediaPresentation\PresentationData;
use App\MediaPresentation\Profiles\ServiceProgramCardPresentationProfile;
use App\Models\TenantServiceProgram;
use App\Money\MoneyBindingRegistry;
use App\Support\Storage\TenantPublicAssetResolver;
use App\Tenant\Expert\ServiceProgramType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TenantServiceProgramResource extends Resource
{
    protected static ?string $model = TenantServiceProgram::class;

    protected static ?string $navigationLabel = null;

    /** В «Каталоге» рядом с прежним местом «курсов» (Motorcycle), скрытым для expert_auto. */
    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $recordTitleAttribute = 'title';

    /** Query string для {@see Tabs::persistTabInQueryString()} на create/edit программы. */
    public const string TAB_QUERY_KEY = 'service_program_tab';

    public const string TAB_KEY_MAIN = 'main';

    public const string TAB_KEY_COVER = 'cover';

    public static function getNavigationLabel(): string
    {
        return self::isBlackDuckTheme() ? 'Услуги' : 'Программы';
    }

    public static function getModelLabel(): string
    {
        return self::isBlackDuckTheme() ? 'Услуга' : 'Программа';
    }

    public static function getPluralModelLabel(): string
    {
        return self::isBlackDuckTheme() ? 'Услуги' : 'Программы';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return self::isBlackDuckTheme() ? 'heroicon-o-rectangle-stack' : 'heroicon-o-academic-cap';
    }

    private static function isBlackDuckTheme(): bool
    {
        $t = \currentTenant();

        return $t !== null && (string) ($t->theme_key ?? '') === 'black_duck';
    }

    private static function formRootTabsLabel(): string
    {
        return self::isBlackDuckTheme() ? 'Услуга' : 'Программа';
    }

    private static function mainSectionDescription(): string
    {
        return self::isBlackDuckTheme()
            ? 'Данные карточек услуг на сайте. Состав страниц и порядок секций — в «Страницы» (конструктор).'
            : 'Данные карточек блока «Программы обучения» на сайте. Состав страниц и порядок секций — в «Страницы» (конструктор).';
    }

    private static function cardCopySectionTitle(): string
    {
        return self::isBlackDuckTheme() ? 'Тексты на карточке услуги' : 'Тексты на карточке программы';
    }

    private static function outcomeRepeaterLabel(): string
    {
        return self::isBlackDuckTheme() ? 'Результат / что получит клиент' : 'Результат / что даёт программа';
    }

    private static function coverSectionTitle(): string
    {
        return self::isBlackDuckTheme() ? 'Обложка карточки услуги' : 'Обложка карточки программы';
    }

    private static function coverHelperAfterUpload(): string
    {
        return self::isBlackDuckTheme()
            ? 'Рекомендуемый размер около 1200×640, формат WebP. Файл сохранится в медиатеке вместе с этой услугой.'
            : 'Рекомендуемый размер около 1200×640, формат WebP. Файл сохранится в медиатеке вместе с этой программой.';
    }

    private static function coverUploadSubdirectory(Get $get): string
    {
        $slug = trim((string) ($get('slug') ?: 'draft'));
        $prefix = self::isBlackDuckTheme() ? 'site/service-programs' : 'expert_auto/programs';

        return $prefix.'/'.$slug;
    }

    /**
     * @return float|int|string|null
     */
    private static function formatViewportFocalState(mixed $state, int $decimals, ?float $ifEmpty = null): mixed
    {
        if ($state === null || $state === '' || $state === false) {
            return $ifEmpty;
        }
        if (! is_numeric($state)) {
            return $state;
        }

        return round((float) $state, $decimals);
    }

    public static function form(Schema $schema): Schema
    {
        // Одна колонка на уровне страницы: иначе EditRecord/CreateRecord по умолчанию ставят 2 колонки,
        // и единственный корневой блок (Tabs) оказывается только в левой ячейке — справа пустая полоса.
        return $schema
            ->columns(1)
            ->components([
                Tabs::make(self::formRootTabsLabel())
                    ->columnSpanFull()
                    ->contained(false)
                    ->persistTabInQueryString(self::TAB_QUERY_KEY)
                    ->tabs([
                        self::TAB_KEY_MAIN => Tab::make('Содержание')
                            ->icon('heroicon-o-document-text')
                            ->key(self::TAB_KEY_MAIN, isInheritable: false)
                            ->id(self::TAB_KEY_MAIN)
                            ->schema([
                                ViewField::make('service_program_form_tab_identity')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->view('filament.forms.components.service-program-form-tab-identity')
                                    ->viewData(function (Get $get): array {
                                        $t = currentTenant();
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
                                        $thumbUrl = ($desktopUrl && $desktopUrl !== '') ? $desktopUrl : (($mobileUrl && $mobileUrl !== '') ? $mobileUrl : null);
                                        $type = ServiceProgramType::tryFrom((string) ($get('program_type') ?? ''));

                                        return [
                                            'title' => (string) ($get('title') ?? ''),
                                            'slug' => (string) ($get('slug') ?? ''),
                                            'programTypeLabel' => $type?->label() ?? '—',
                                            'coverThumbUrl' => $thumbUrl,
                                            'tabQueryKey' => self::TAB_QUERY_KEY,
                                            'coverTabKey' => self::TAB_KEY_COVER,
                                        ];
                                    })
                                    ->columnSpanFull(),
                                Section::make('Основное')
                                    ->description(self::mainSectionDescription())
                                    ->extraAttributes(['data-setup-target' => 'programs.program_form'])
                                    ->schema([
                                        TextInput::make('slug')
                                            ->label('URL-идентификатор')
                                            ->required()
                                            ->maxLength(128)
                                            ->helperText('Короткий адрес в ссылке, без пробелов. Уникален внутри клиента.'),
                                        TextInput::make('title')
                                            ->label('Название')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true),
                                        Textarea::make('teaser')
                                            ->label('Короткий лид')
                                            ->rows(2)
                                            ->columnSpanFull()
                                            ->live(onBlur: true),
                                        Textarea::make('description')
                                            ->label('Описание')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->live(onBlur: true),
                                        Select::make('program_type')
                                            ->label('Тип')
                                            ->native(true)
                                            ->options(collect(ServiceProgramType::cases())->mapWithKeys(
                                                fn (ServiceProgramType $t): array => [$t->value => $t->label()]
                                            ))
                                            ->required()
                                            ->live(),
                                        TextInput::make('duration_label')
                                            ->label('Длительность (текстом)')
                                            ->maxLength(255)
                                            ->live(onBlur: true),
                                        TextInput::make('format_label')
                                            ->label('Формат занятия')
                                            ->maxLength(255)
                                            ->live(onBlur: true),
                                        TenantMoneyForms::moneyTextInput('price_amount', MoneyBindingRegistry::TENANT_SERVICE_PROGRAM_PRICE_AMOUNT, 'Цена', required: false, nullableStorage: true)
                                            ->helperText('Ввод в человекочитаемом виде по настройкам «Деньги / Валюта». Оставьте пустым для «По запросу».')
                                            ->live(onBlur: true),
                                        TextInput::make('price_prefix')
                                            ->label('Префикс цены («от» и т.п.)')
                                            ->maxLength(32)
                                            ->live(onBlur: true),
                                        Toggle::make('is_featured')
                                            ->label('Избранное (широкая карточка)')
                                            ->live(),
                                        Toggle::make('is_visible')->label('Видимость на сайте')->default(true),
                                        TextInput::make('sort_order')
                                            ->label('Порядок в списке')
                                            ->numeric()
                                            ->default(0),
                                    ])->columns(2),
                                Section::make(self::cardCopySectionTitle())
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
                                            ->live()
                                            ->columnSpanFull(),
                                        Repeater::make('outcomes_json')
                                            ->label(self::outcomeRepeaterLabel())
                                            ->schema([
                                                TextInput::make('text')
                                                    ->label('Пункт')
                                                    ->maxLength(500)
                                                    ->required(),
                                            ])
                                            ->defaultItems(0)
                                            ->addActionLabel('Добавить пункт')
                                            ->reorderable()
                                            ->live()
                                            ->columnSpanFull(),
                                    ])->columns(1),
                            ]),
                        self::TAB_KEY_COVER => Tab::make('Обложка')
                            ->icon('heroicon-o-photo')
                            ->key(self::TAB_KEY_COVER, isInheritable: false)
                            ->id(self::TAB_KEY_COVER)
                            ->extraAttributes([
                                'class' => 'svc-program-cover-tab',
                                'data-svc-program-cover-tab' => 'true',
                            ])
                            ->schema(self::buildCoverTabEditorShellSchema()),
                    ]),
            ]);
    }

    /**
     * Вкладка «Обложка»: колонка — поля формы на полную ширину; под ними превью кадр+сайт на полную ширину.
     * Раньше превью жило в «rail» (половина экрана) — внутри неё кадр и WYSIWYG делили 50/50 и всё сжималось.
     *
     * @return array<int, Group>
     */
    private static function buildCoverTabEditorShellSchema(): array
    {
        return [
            Group::make()
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'svc-program-cover-tab-stack w-full max-w-full',
                ])
                ->schema([
                    Group::make()
                        ->extraAttributes([
                            'class' => 'svc-program-cover-tab-main min-w-0 flex flex-col gap-6',
                        ])
                        ->schema([
                            Section::make(self::coverSectionTitle())
                                ->description('На сайте сверху карточки показывается широкий баннер. Для компьютера загрузите горизонтальное изображение (~1200×640, WebP). Для телефона можно отдельно выбрать вертикальное (~720×1040); если не загрузить — на узком экране подставится баннер для компьютера.')
                                ->schema(self::buildCoverTabLeftMainFieldsSchema()),
                            Section::make('Дополнительно: X, Y, Zoom, высота')
                                ->description('Те же значения, что слайдеры в превью. Основной ввод — в превью; здесь — точные числа.')
                                ->extraAttributes(['data-svc-focal-numeric-extras' => 'true'])
                                ->collapsed()
                                ->schema(self::buildCoverTabAdvancedNumericSchema()),
                        ]),
                    Group::make()
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'svc-program-cover-tab-full-preview min-w-0 w-full max-w-full'])
                        ->schema([
                            Group::make()
                                ->columnSpanFull()
                                ->extraAttributes(['class' => 'svc-program-cover-tab-preview w-full min-w-0 max-w-full'])
                                ->schema(self::buildCoverTabPreviewViewFieldSchema()),
                        ]),
                ]),
        ];
    }

    /**
     * Левая колонка, основной блок: баннеры, alt, sync (числовой advanced — в отдельной секции).
     *
     * @return array<int, Component>
     */
    private static function buildCoverTabLeftMainFieldsSchema(): array
    {
        return [
            TenantPublicImagePicker::make('cover_image_ref')
                ->label('Баннер для компьютера (широкий)')
                ->uploadPublicSiteSubdirectory(fn (Get $get): string => self::coverUploadSubdirectory($get))
                ->helperText(self::coverHelperAfterUpload())
                ->live(),
            TenantPublicImagePicker::make('cover_mobile_ref')
                ->label('Баннер для телефона (портрет, по желанию)')
                ->uploadPublicSiteSubdirectory(fn (Get $get): string => self::coverUploadSubdirectory($get))
                ->helperText('Рекомендуемый размер около 720×1040. Если не загрузить — на телефоне используется баннер для компьютера.')
                ->live(),
            TextInput::make('cover_image_alt')
                ->label('Alt-текст для изображения')
                ->maxLength(500)
                ->live(onBlur: true),
            Hidden::make('cover_presentation.version')
                ->default(PresentationData::CURRENT_VERSION)
                ->dehydrated(),
            Toggle::make('cover_focal_sync_mobile_desktop')
                ->label('Синхронизировать mobile и desktop')
                ->helperText('Включено: перетаскивание и сброс меняют оба кадра. Выключено: правьте отдельно или скопируйте кнопками в превью.')
                ->default(true)
                ->dehydrated(false)
                ->live(),
        ];
    }

    /**
     * @return array<int, ViewField>
     */
    private static function buildCoverTabPreviewViewFieldSchema(): array
    {
        return [
            ViewField::make('cover_presentation_preview')
                ->hiddenLabel()
                ->view('filament.forms.components.service-program-cover-preview')
                ->viewData(fn (Get $get): array => ServiceProgramCoverPreviewViewDataFactory::make($get)),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function buildCoverTabAdvancedNumericSchema(): array
    {
        return [
            Section::make('Кадр mobile (узкий экран, до 1023px на сайте)')
                ->description('До 1024px по ширине, высота обложки — mobile.')
                ->schema([
                    TextInput::make('cover_presentation.viewport_focal_map.mobile.x')
                        ->label('X %')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 1))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.1)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('cover_presentation.viewport_focal_map.mobile.y')
                        ->label('Y %')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 1))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.1)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('cover_presentation.viewport_focal_map.mobile.scale')
                        ->label('Zoom (множитель)')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 2))
                        ->numeric()
                        ->minValue(ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN)
                        ->maxValue(ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX)
                        ->step(ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP)
                        ->required()
                        ->live(onBlur: true)
                        ->helperText(sprintf(
                            'Диапазон %.2f–%.2f, шаг %.2f (как в превью).',
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN,
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX,
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP
                        )),
                    TextInput::make('cover_presentation.viewport_focal_map.mobile.height_factor')
                        ->label('Height (множитель области фото)')
                        ->formatStateUsing(
                            fn ($s) => self::formatViewportFocalState(
                                $s,
                                2,
                                ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT,
                            ),
                        )
                        ->numeric()
                        ->minValue(ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MIN)
                        ->maxValue(ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MAX)
                        ->step(ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_STEP)
                        ->required()
                        ->live(onBlur: true)
                        ->helperText('1,0 = как в теме; больше — выше баннер, меньше — ниже.'),
                ])->columns(2),
            Section::make('Кадр desktop (от 1024px)')
                ->description('Широкий viewport на сайте.')
                ->schema([
                    TextInput::make('cover_presentation.viewport_focal_map.desktop.x')
                        ->label('X %')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 1))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.1)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('cover_presentation.viewport_focal_map.desktop.y')
                        ->label('Y %')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 1))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.1)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('cover_presentation.viewport_focal_map.desktop.scale')
                        ->label('Zoom (множитель)')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 2))
                        ->numeric()
                        ->minValue(ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN)
                        ->maxValue(ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX)
                        ->step(ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP)
                        ->required()
                        ->live(onBlur: true)
                        ->helperText(sprintf(
                            'Диапазон %.2f–%.2f, шаг %.2f (как в превью).',
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN,
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX,
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP
                        )),
                    TextInput::make('cover_presentation.viewport_focal_map.desktop.height_factor')
                        ->label('Height (множитель области фото)')
                        ->formatStateUsing(
                            fn ($s) => self::formatViewportFocalState(
                                $s,
                                2,
                                ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_DEFAULT,
                            ),
                        )
                        ->numeric()
                        ->minValue(ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MIN)
                        ->maxValue(ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_MAX)
                        ->step(ServiceProgramCardPresentationProfile::HEIGHT_FACTOR_STEP)
                        ->required()
                        ->live(onBlur: true)
                        ->helperText('1,0 = как в теме; больше — выше, меньше — ниже.'),
                ])->columns(2),
            Section::make('Кадр tablet (опционально, только превью в админке)')
                ->description('На сайте планшет использует ветку mobile; JSON tablet — тонкая настройка, не обязателен.')
                ->icon('heroicon-m-information-circle')
                ->extraAttributes([
                    'class' => '[&_.fi-section-header]:opacity-80 [&_.fi-section-header]:text-gray-600 dark:[&_.fi-section-header]:text-gray-400',
                ])
                ->schema([
                    TextInput::make('cover_presentation.viewport_focal_map.tablet.x')
                        ->label('X %')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 1))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.1)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('cover_presentation.viewport_focal_map.tablet.y')
                        ->label('Y %')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 1))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.1)
                        ->required()
                        ->live(onBlur: true),
                    TextInput::make('cover_presentation.viewport_focal_map.tablet.scale')
                        ->label('Zoom (множитель)')
                        ->formatStateUsing(fn ($s) => self::formatViewportFocalState($s, 2))
                        ->numeric()
                        ->minValue(ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN)
                        ->maxValue(ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX)
                        ->step(ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP)
                        ->required()
                        ->live(onBlur: true)
                        ->helperText(sprintf(
                            '%.2f–%.2f, шаг %.2f.',
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_MIN,
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_MAX,
                            ServiceProgramCardPresentationProfile::FRAMING_SCALE_STEP
                        )),
                ])->columns(1)->compact(),
        ];
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
            ->recordActions([EditAction::make()]);
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
        $k = currentTenant()?->themeKey() ?? '';

        return in_array($k, ['expert_auto', 'black_duck'], true);
    }
}
