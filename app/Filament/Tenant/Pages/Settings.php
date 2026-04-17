<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Shared\TenantAnalyticsFormSchema;
use App\Livewire\Concerns\InteractsWithTenantPublicFilePicker;
use App\Models\PlatformSetting;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Money\Enums\MoneyFractionDisplayMode;
use App\Money\TenantMoneySettingsResolver;
use App\Rules\OptionalRussianPhone;
use App\Services\Analytics\AnalyticsSettingsPersistence;
use App\Support\Accessibility\WcagContrast;
use App\Support\Analytics\AnalyticsSettingsData;
use App\Support\Analytics\AnalyticsSettingsFormMapper;
use App\Support\RussianPhone;
use App\Support\Storage\TenantStorageDisks;
use App\Tenant\Expert\TenantEnrollmentCtaConfig;
use App\Tenant\StorageQuota\StorageQuotaExceededException;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;
use UnitEnum;

class Settings extends Page
{
    use InteractsWithTenantPublicFilePicker;
    use WithFileUploads;

    /**
     * Form state keys (underscore) → dotted keys passed to TenantSetting / Setting (must match getSettingsData()).
     *
     * @var array<string, string>
     */
    private const FORM_FIELD_TO_SETTING_KEY = [
        'general_site_name' => 'general.site_name',
        'general_short_description' => 'general.short_description',
        'general_domain' => 'general.domain',
        'branding_logo' => 'branding.logo',
        'branding_logo_path' => 'branding.logo_path',
        'branding_primary_color' => 'branding.primary_color',
        'branding_favicon' => 'branding.favicon',
        'branding_favicon_path' => 'branding.favicon_path',
        'branding_hero' => 'branding.hero',
        'branding_hero_path' => 'branding.hero_path',
        'contacts_phone' => 'contacts.phone',
        'contacts_phone_alt' => 'contacts.phone_alt',
        'contacts_email' => 'contacts.email',
        'contacts_whatsapp' => 'contacts.whatsapp',
        'contacts_telegram' => 'contacts.telegram',
        'contacts_address' => 'contacts.address',
        'contacts_hours' => 'contacts.hours',
        'programs_cta_behavior' => 'programs.cta_behavior',
        'programs_enrollment_page_slug' => 'programs.enrollment_page_slug',
        'programs_modal_title' => 'programs.modal_title',
        'programs_modal_success_message' => 'programs.modal_success_message',
        'reviews_public_submit_enabled' => 'reviews.public_submit_enabled',
        'reviews_moderation_enabled' => 'reviews.moderation_enabled',
        'reviews_form_show_rating' => 'reviews.form_show_rating',
        'reviews_success_message_pending' => 'reviews.success_message_pending',
        'reviews_success_message_published' => 'reviews.success_message_published',
    ];

    protected static ?string $navigationLabel = 'Настройки';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Настройки сайта';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage_settings');
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('manage_settings'), 403);
        // manage_settings: в дефолтной матрице pivot только tenant_owner / tenant_admin.
        $this->data = $this->getSettingsData();
    }

    protected function getSettingsData(): array
    {
        $tenant = \currentTenant();
        if ($tenant) {
            return [
                'general_site_name' => TenantSetting::getForTenant($tenant->id, 'general.site_name', $tenant->defaultPublicSiteName()),
                'general_short_description' => TenantSetting::getForTenant($tenant->id, 'general.short_description', ''),
                'general_domain' => $this->resolvedGeneralDomainFormValue($tenant),
                'branding_logo' => TenantSetting::getForTenant($tenant->id, 'branding.logo', ''),
                'branding_logo_path' => TenantSetting::getForTenant($tenant->id, 'branding.logo_path', ''),
                'branding_primary_color' => TenantSetting::getForTenant($tenant->id, 'branding.primary_color', '#f59e0b'),
                'branding_favicon' => TenantSetting::getForTenant($tenant->id, 'branding.favicon', ''),
                'branding_favicon_path' => TenantSetting::getForTenant($tenant->id, 'branding.favicon_path', ''),
                'branding_hero' => TenantSetting::getForTenant($tenant->id, 'branding.hero', ''),
                'branding_hero_path' => TenantSetting::getForTenant($tenant->id, 'branding.hero_path', ''),
                'contacts_phone' => RussianPhone::toMasked(TenantSetting::getForTenant($tenant->id, 'contacts.phone', '')),
                'contacts_phone_alt' => RussianPhone::toMasked(TenantSetting::getForTenant($tenant->id, 'contacts.phone_alt', '')),
                'contacts_email' => TenantSetting::getForTenant($tenant->id, 'contacts.email', ''),
                'contacts_whatsapp' => TenantSetting::getForTenant($tenant->id, 'contacts.whatsapp', ''),
                'contacts_telegram' => TenantSetting::getForTenant($tenant->id, 'contacts.telegram', ''),
                'contacts_address' => TenantSetting::getForTenant($tenant->id, 'contacts.address', ''),
                'contacts_hours' => TenantSetting::getForTenant($tenant->id, 'contacts.hours', ''),
                'programs_cta_behavior' => TenantSetting::getForTenant(
                    $tenant->id,
                    'programs.cta_behavior',
                    TenantEnrollmentCtaConfig::MODE_MODAL,
                ),
                'programs_enrollment_page_slug' => TenantSetting::getForTenant(
                    $tenant->id,
                    'programs.enrollment_page_slug',
                    'programs',
                ),
                'programs_modal_title' => TenantSetting::getForTenant(
                    $tenant->id,
                    'programs.modal_title',
                    (new TenantEnrollmentCtaConfig($tenant))->modalTitle(),
                ),
                'programs_modal_success_message' => TenantSetting::getForTenant(
                    $tenant->id,
                    'programs.modal_success_message',
                    (new TenantEnrollmentCtaConfig($tenant))->modalSuccessMessage(),
                ),
                'reviews_public_submit_enabled' => (bool) TenantSetting::getForTenant($tenant->id, 'reviews.public_submit_enabled', true),
                'reviews_moderation_enabled' => (bool) TenantSetting::getForTenant($tenant->id, 'reviews.moderation_enabled', true),
                'reviews_form_show_rating' => (bool) TenantSetting::getForTenant($tenant->id, 'reviews.form_show_rating', true),
                'reviews_success_message_pending' => TenantSetting::getForTenant(
                    $tenant->id,
                    'reviews.success_message_pending',
                    'Спасибо! Ваш отзыв отправлен на проверку и появится на сайте после модерации.',
                ),
                'reviews_success_message_published' => TenantSetting::getForTenant(
                    $tenant->id,
                    'reviews.success_message_published',
                    'Спасибо! Ваш отзыв успешно отправлен.',
                ),
                ...AnalyticsSettingsFormMapper::toFormState(
                    app(AnalyticsSettingsPersistence::class)->load((int) $tenant->id)
                ),
                ...self::moneyFormState($tenant),
            ];
        }

        return [
            'general_site_name' => Setting::get('general.site_name', config('app.name')),
            'general_domain' => Setting::get('general.domain', config('app.url')),
            'branding_logo' => '',
            'branding_primary_color' => '#f59e0b',
            'branding_favicon' => '',
            'contacts_phone' => RussianPhone::toMasked(Setting::get('contacts.phone', '')),
            'contacts_phone_alt' => RussianPhone::toMasked(Setting::get('contacts.phone_alt', '')),
            'contacts_email' => Setting::get('contacts.email', ''),
            'contacts_whatsapp' => Setting::get('contacts.whatsapp', ''),
            'contacts_telegram' => Setting::get('contacts.telegram', ''),
            'contacts_address' => Setting::get('contacts.address', ''),
            'contacts_hours' => Setting::get('contacts.hours', ''),
            ...AnalyticsSettingsFormMapper::toFormState(AnalyticsSettingsData::defaultEmpty()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $tenant = \currentTenant();

        $siteIdentityFields = [
            TextInput::make('general_site_name')
                ->label('Название сайта')
                ->helperText('Показывается в шапке, заголовках и письмах, если тема не задаёт иначе.')
                ->placeholder('Например: MotoLevins Сочи')
                ->extraAttributes(['data-setup-target' => 'settings.site_name']),
            Textarea::make('general_short_description')
                ->label('Краткое описание / оффер')
                ->rows(3)
                ->maxLength(2000)
                ->helperText('Коротко о том, что предлагаете посетителю. Используется в готовности сайта и подсказках.')
                ->visible(fn (): bool => $tenant !== null)
                ->extraAttributes([
                    'data-setup-target' => 'settings.tagline_or_short_description',
                    'data-setup-focus-target' => '',
                ]),
        ];
        if ($tenant === null) {
            $siteIdentityFields[] = TextInput::make('general_domain')
                ->label('Основной URL сайта')
                ->url()
                ->nullable()
                ->helperText(
                    'Полный адрес с https://. Если не заполнять и сохранить пустым — подставится адрес из доменов клиента.'
                );
        }

        $advancedSections = [];
        if ($tenant !== null) {
            $advancedSections[] = Section::make(__('tenant_admin_settings.sections.canonical_url'))
                ->description(__('tenant_admin_settings.sections.canonical_url_hint'))
                ->schema([
                    TextInput::make('general_domain')
                        ->label('Канонический URL сайта')
                        ->url()
                        ->nullable()
                        ->extraAttributes(['data-setup-target' => 'settings.public_canonical_url'])
                        ->helperText(
                            'Полный адрес с https://. Оставьте пустым и сохраните — будет использоваться адрес из раздела «Свой домен» / основной домен без дублирования здесь.'
                        ),
                ])
                ->columns(2);

            $advancedSections[] = Section::make(__('tenant_admin_settings.sections.money'))
                ->description(new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-400">'
                    .e(__('tenant_admin_settings.sections.money_hint'))
                    .'</p>'
                ))
                ->collapsed()
                ->collapsible()
                ->schema([
                    Select::make('money_base_currency_code')
                        ->label('Основная валюта')
                        ->options(fn (): array => self::moneyCurrencySelectOptions())
                        ->required()
                        ->native(true),
                    Select::make('money_fraction_display_mode')
                        ->label('Копейки и дробь в ценах')
                        ->options([
                            MoneyFractionDisplayMode::Auto->value => 'Автоматически — без лишних нулей',
                            MoneyFractionDisplayMode::Always->value => 'Всегда полная дробь (как в документе)',
                            MoneyFractionDisplayMode::Never->value => 'Без дробной части на экране',
                        ])
                        ->required()
                        ->native(true),
                    Select::make('money_display_scale_exponent')
                        ->label('Разрядность сумм (крупные числа)')
                        ->helperText('Меняет только отображение и ввод в формах, не пересчитывает уже сохранённые цены.')
                        ->options([
                            0 => 'Обычные суммы (рубль или единица валюты)',
                            3 => 'Тысячи',
                            6 => 'Миллионы',
                        ])
                        ->required()
                        ->native(true),
                    TextInput::make('money_display_unit_label_override')
                        ->label('Своя подпись к сумме (необязательно)')
                        ->maxLength(32)
                        ->helperText('Только при обычных суммах. Пусто — обозначение валюты из справочника.'),
                    Toggle::make('money_multi_currency_enabled')
                        ->label('Несколько валют на сайте')
                        ->visible(fn (): bool => (bool) PlatformSetting::get('money.tenant_multicurrency_allowed', false))
                        ->helperText('Дополнительные валюты задаются вручную; курсы не подтягиваются автоматически.'),
                    TextInput::make('money_additional_currency_codes')
                        ->label('Дополнительные валюты (коды через запятую)')
                        ->visible(fn (): bool => (bool) PlatformSetting::get('money.tenant_multicurrency_allowed', false))
                        ->placeholder('USD, EUR')
                        ->maxLength(255),
                ])
                ->columns(2);
        }

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make(__('tenant_admin_settings.tabs_group'))
                    ->persistTabInQueryString('settings_tab')
                    ->tabs([
                        'general' => Tab::make(__('tenant_admin_settings.tabs.general'))
                            // Стабильные id для persistTabInQueryString('settings_tab') и гида запуска (SetupItemRegistry.settingsTabKey).
                            ->id('general')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Section::make(__('tenant_admin_settings.sections.site_identity'))
                                    ->description(__('tenant_admin_settings.sections.site_identity_hint'))
                                    ->extraAttributes(['data-setup-section' => 'site_identity'])
                                    ->schema($siteIdentityFields)->columns(2),

                                Section::make('Контакты')
                                    ->description('Телефоны и мессенджеры обычно выводятся в шапке, подвале и на странице контактов.')
                                    ->extraAttributes(['data-setup-section' => 'contacts_block'])
                                    ->schema([
                                        TextInput::make('contacts_phone')
                                            ->label('Телефон')
                                            ->mask('+7 (999) 999-99-99')
                                            ->placeholder('+7 (___) ___-__-__')
                                            ->rules([new OptionalRussianPhone])
                                            ->helperText('Маска для российского номера. После сохранения в базе хранится в виде +7XXXXXXXXXX.')
                                            ->extraAttributes(['data-setup-target' => 'contact_channels.primary_phone']),
                                        TextInput::make('contacts_phone_alt')
                                            ->label('Дополнительный телефон')
                                            ->mask('+7 (999) 999-99-99')
                                            ->placeholder('+7 (___) ___-__-__')
                                            ->rules([new OptionalRussianPhone]),
                                        TextInput::make('contacts_email')->label('Email')->email()->placeholder('hello@example.com'),
                                        TextInput::make('contacts_whatsapp')
                                            ->label('WhatsApp')
                                            ->placeholder('Номер или ссылка https://wa.me/…')
                                            ->helperText('Без маски: можно номер или полную ссылку WhatsApp.'),
                                        TextInput::make('contacts_telegram')->label('Telegram')->placeholder('@username или ссылка t.me/…'),
                                        Textarea::make('contacts_address')->label('Адрес')->rows(2),
                                        Textarea::make('contacts_hours')->label('Часы работы')->rows(2)->placeholder('Например: Пн–Вс 9:00–21:00'),
                                    ])->columns(2),
                            ]),

                        'appearance' => Tab::make(__('tenant_admin_settings.tabs.appearance'))
                            ->id('appearance')
                            ->icon('heroicon-o-paint-brush')
                            ->visible(fn (): bool => $tenant !== null)
                            ->schema([
                                Section::make('Оформление сайта')
                                    ->description('Логотип и цвета для публичного сайта. Удобнее загрузить файл слева; справа — запасная ссылка, если файл не используете. Загруженный файл важнее ссылки.')
                                    ->schema([
                                        Grid::make(2)
                                            ->extraAttributes(['data-setup-target' => 'settings.logo'])
                                            ->schema([
                                                TenantPublicImagePicker::make('branding_logo_path')
                                                    ->label('Логотип (файл)')
                                                    ->extraAttributes(['data-setup-focus-target' => ''])
                                                    ->uploadSlotSelector('[data-settings-tenant-upload-input]')
                                                    ->uploadPublicSiteSubdirectory('site/logo')
                                                    ->helperText('PNG, JPG, WebP. До 4 МБ; можно выбрать из каталога.'),
                                                TextInput::make('branding_logo')
                                                    ->label('Логотип (ссылка, запасной вариант)')
                                                    ->url()
                                                    ->placeholder('https://...')
                                                    ->helperText('Только если файл не задан.'),
                                            ]),
                                        TextInput::make('branding_primary_color')
                                            ->label('Основной цвет')
                                            ->type('color')
                                            ->columnSpanFull()
                                            ->helperText('Акцентные кнопки и ссылки на сайте.'),
                                        Grid::make(2)
                                            ->extraAttributes(['data-setup-section' => 'branding_favicon', 'data-setup-target' => 'settings.favicon'])
                                            ->schema([
                                                TenantPublicImagePicker::make('branding_favicon_path')
                                                    ->label('Иконка сайта (файл)')
                                                    ->extraAttributes(['data-setup-focus-target' => ''])
                                                    ->uploadSlotSelector('[data-settings-tenant-upload-input]')
                                                    ->uploadPublicSiteSubdirectory('site/favicon')
                                                    ->helperText('PNG, ICO, SVG. До 4 МБ; для иконки лучше заранее оптимизировать файл.'),
                                                TextInput::make('branding_favicon')
                                                    ->label('Иконка сайта (ссылка)')
                                                    ->url()
                                                    ->placeholder('https://...')
                                                    ->helperText('Только если файл не задан.'),
                                            ]),
                                        Grid::make(2)
                                            ->extraAttributes(['data-setup-target' => 'settings.branding_hero_social_image'])
                                            ->schema([
                                                TenantPublicImagePicker::make('branding_hero_path')
                                                    ->label('Картинка для шапки и соцсетей (файл)')
                                                    ->extraAttributes(['data-setup-focus-target' => ''])
                                                    ->uploadSlotSelector('[data-settings-tenant-upload-input]')
                                                    ->uploadPublicSiteSubdirectory('site/hero')
                                                    ->helperText('Крупное изображение; как именно показывается — зависит от темы сайта.'),
                                                TextInput::make('branding_hero')
                                                    ->label('Картинка для шапки (ссылка)')
                                                    ->url()
                                                    ->nullable()
                                                    ->helperText('Только если файл не задан.'),
                                            ]),
                                    ])
                                    ->columns(1),
                            ]),

                        'enrollment' => Tab::make(__('tenant_admin_settings.tabs.enrollment'))
                            ->id('enrollment')
                            ->icon('heroicon-o-academic-cap')
                            ->visible(fn (): bool => $tenant !== null && in_array((string) ($tenant->theme_key ?? ''), ['expert_auto', 'advocate_editorial'], true))
                            ->schema([
                                Section::make('Кнопка «Записаться»')
                                    ->description('Для тем с программами и записями (например автошкола, адвокат). Обычно удобнее окно с формой; отдельная страница — если нужен длинный сценарий.')
                                    ->schema([
                                        Select::make('programs_cta_behavior')
                                            ->label('Что делает кнопка')
                                            ->options([
                                                TenantEnrollmentCtaConfig::MODE_MODAL => 'Открыть окно с формой',
                                                TenantEnrollmentCtaConfig::MODE_PAGE => 'Перейти на другую страницу сайта',
                                                TenantEnrollmentCtaConfig::MODE_SCROLL => 'Прокрутить к форме на этой странице (старый вариант)',
                                            ])
                                            ->required()
                                            ->native(true),
                                        TextInput::make('programs_enrollment_page_slug')
                                            ->label('Адрес страницы записи')
                                            ->maxLength(128)
                                            ->regex('/^[a-z0-9\-]+$/')
                                            ->helperText('Часть URL без слешей, латиница и дефисы. Пример: programs — откроется страница /programs. Для предзаполнения программы можно добавить ?program= в ссылке.'),
                                        TextInput::make('programs_modal_title')
                                            ->label('Заголовок окна с формой')
                                            ->maxLength(255),
                                        Textarea::make('programs_modal_success_message')
                                            ->label('Сообщение после отправки')
                                            ->rows(2)
                                            ->maxLength(1000),
                                    ])
                                    ->columns(2),
                            ]),

                        'reviews' => Tab::make(__('tenant_admin_settings.tabs.reviews'))
                            ->id('reviews')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->visible(fn (): bool => $tenant !== null)
                            ->schema([
                                Section::make('Публичная форма отзывов')
                                    ->description('Кнопка и форма на странице отзывов и в блоке «Отзывы» в конструкторе. По умолчанию новые отзывы идут на модерацию и не публикуются сразу.')
                                    ->schema([
                                        Toggle::make('reviews_public_submit_enabled')
                                            ->label('Разрешить отправку отзывов с сайта')
                                            ->helperText('Если выключено, кнопка и форма на сайте не показываются, API возвращает отказ.')
                                            ->default(true),
                                        Toggle::make('reviews_moderation_enabled')
                                            ->label('Модерация перед публикацией')
                                            ->helperText('Включено: новый отзыв в статусе «На модерации», на сайте не виден, пока вы не одобрите в разделе «Отзывы».')
                                            ->default(true),
                                        Toggle::make('reviews_form_show_rating')
                                            ->label('Показывать оценку (звёзды) в форме')
                                            ->default(true),
                                        Textarea::make('reviews_success_message_pending')
                                            ->label('Сообщение после отправки (с модерацией)')
                                            ->rows(2)
                                            ->maxLength(1000),
                                        Textarea::make('reviews_success_message_published')
                                            ->label('Сообщение после отправки (без модерации)')
                                            ->rows(2)
                                            ->maxLength(1000),
                                    ])
                                    ->columns(2),
                            ]),

                        'analytics' => Tab::make(__('tenant_admin_settings.tabs.analytics'))
                            ->id('analytics')
                            ->icon('heroicon-o-chart-bar')
                            ->visible(fn (): bool => $tenant !== null)
                            ->schema([
                                TenantAnalyticsFormSchema::section(true),
                            ]),

                        'advanced' => Tab::make(__('tenant_admin_settings.tabs.advanced'))
                            ->id('advanced')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->visible(fn (): bool => $tenant !== null)
                            ->schema($advancedSections),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->getSchema('form')->getState();
        $tenant = \currentTenant();

        if ($tenant) {
            try {
                $this->assertBrandingUploadsWithinQuota($tenant, $data);
            } catch (StorageQuotaExceededException $e) {
                Notification::make()
                    ->title($e->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        }

        if ($tenant) {
            try {
                $persistence = app(AnalyticsSettingsPersistence::class);
                $before = $persistence->load((int) $tenant->id);
                $new = AnalyticsSettingsFormMapper::toValidatedData($data);
                $persistence->save((int) $tenant->id, $new, Auth::user(), $before);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $messages) {
                    Notification::make()
                        ->title($messages[0] ?? 'Ошибка валидации')
                        ->danger()
                        ->send();
                }

                return;
            }
        }

        if ($tenant !== null) {
            $this->persistMoneySettings($tenant, $data);
        }

        if ($tenant !== null && array_key_exists('general_domain', $data)) {
            $raw = trim((string) ($data['general_domain'] ?? ''));
            if ($raw === '' || ! filter_var($raw, FILTER_VALIDATE_URL)) {
                TenantSetting::forgetForTenant((int) $tenant->id, 'general.domain');
            } else {
                TenantSetting::setForTenant((int) $tenant->id, 'general.domain', rtrim($raw, '/'));
            }
            unset($data['general_domain']);
        }

        foreach ($data as $field => $value) {
            if (! array_key_exists($field, self::FORM_FIELD_TO_SETTING_KEY)) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $settingKey = self::FORM_FIELD_TO_SETTING_KEY[$field];
            $stored = $value === null ? '' : (string) $value;

            if (in_array($field, ['contacts_phone', 'contacts_phone_alt'], true)) {
                $normalized = RussianPhone::normalize($stored);
                $stored = $normalized ?? '';
            }

            if ($tenant) {
                TenantSetting::setForTenant($tenant->id, $settingKey, $stored);
            } else {
                Setting::set($settingKey, $stored);
            }
        }

        if ($tenant !== null) {
            $this->notifyIfBrandingPrimaryLowContrast(isset($data['branding_primary_color']) ? (string) $data['branding_primary_color'] : null);
        }

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public static function formFieldToSettingKeyMap(): array
    {
        return self::FORM_FIELD_TO_SETTING_KEY;
    }

    /**
     * Effective public base URL for the form: explicit tenant_settings.general.domain if valid, else same fallback as runtime (active request host, primary domain, app.url).
     */
    /**
     * @return array<string, mixed>
     */
    private static function moneyFormState(Tenant $tenant): array
    {
        $resolver = app(TenantMoneySettingsResolver::class);
        $defaults = $resolver->platformDefaultsForNewTenants();
        $tid = (int) $tenant->id;
        $additional = TenantSetting::getForTenant($tid, 'money.additional_currency_codes', []);
        $additionalStr = '';
        if (is_array($additional) && $additional !== []) {
            $additionalStr = implode(', ', $additional);
        }

        return [
            'money_base_currency_code' => strtoupper(trim((string) TenantSetting::getForTenant(
                $tid,
                'money.base_currency_code',
                (string) ($defaults['base_currency_code'] ?? $tenant->currency ?? 'RUB')
            ))),
            'money_fraction_display_mode' => (string) TenantSetting::getForTenant(
                $tid,
                'money.fraction_display_mode',
                (string) ($defaults['fraction_display_mode'] ?? 'auto')
            ),
            'money_display_scale_exponent' => (int) TenantSetting::getForTenant(
                $tid,
                'money.display_scale_exponent',
                (int) ($defaults['display_scale_exponent'] ?? 0)
            ),
            'money_display_unit_label_override' => (string) TenantSetting::getForTenant($tid, 'money.display_unit_label_override', ''),
            'money_multi_currency_enabled' => (bool) TenantSetting::getForTenant(
                $tid,
                'money.multi_currency_enabled',
                (bool) ($defaults['multi_currency_enabled'] ?? false)
            ),
            'money_additional_currency_codes' => $additionalStr,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function moneyCurrencySelectOptions(): array
    {
        $resolver = app(TenantMoneySettingsResolver::class);
        $opts = [];
        foreach ($resolver->platformCurrenciesByCode() as $c) {
            if (! $c->active) {
                continue;
            }
            $opts[$c->code] = $c->code.' — '.$c->name;
        }
        $tenant = currentTenant();
        if ($tenant !== null) {
            $cur = strtoupper(trim((string) ($tenant->currency ?? '')));
            if ($cur !== '' && ! isset($opts[$cur])) {
                $opts[$cur] = $cur.' (текущая колонка tenants.currency)';
            }
        }

        return $opts;
    }

    private function persistMoneySettings(Tenant $tenant, array &$data): void
    {
        if (! array_key_exists('money_base_currency_code', $data)) {
            return;
        }

        $tid = (int) $tenant->id;
        $base = strtoupper(trim((string) ($data['money_base_currency_code'] ?? 'RUB')));
        TenantSetting::setForTenant($tid, 'money.base_currency_code', $base);

        $fraction = (string) ($data['money_fraction_display_mode'] ?? 'auto');
        if (MoneyFractionDisplayMode::tryFrom($fraction) === null) {
            $fraction = MoneyFractionDisplayMode::Auto->value;
        }
        TenantSetting::setForTenant($tid, 'money.fraction_display_mode', $fraction);

        $scale = (int) ($data['money_display_scale_exponent'] ?? 0);
        if (! in_array($scale, [0, 3, 6], true)) {
            $scale = 0;
        }
        TenantSetting::setForTenant($tid, 'money.display_scale_exponent', (string) $scale, 'integer');

        $override = trim((string) ($data['money_display_unit_label_override'] ?? ''));
        if ($override === '') {
            TenantSetting::forgetForTenant($tid, 'money.display_unit_label_override');
        } else {
            TenantSetting::setForTenant($tid, 'money.display_unit_label_override', $override);
        }

        $multiAllowed = (bool) PlatformSetting::get('money.tenant_multicurrency_allowed', false);
        $multi = $multiAllowed && (bool) ($data['money_multi_currency_enabled'] ?? false);
        TenantSetting::setForTenant($tid, 'money.multi_currency_enabled', $multi ? '1' : '0', 'boolean');

        $rawCodes = trim((string) ($data['money_additional_currency_codes'] ?? ''));
        $codes = [];
        if ($rawCodes !== '') {
            foreach (preg_split('/[,\s]+/', $rawCodes) ?: [] as $p) {
                $c = strtoupper(trim((string) $p));
                if ($c !== '' && $c !== $base) {
                    $codes[] = $c;
                }
            }
            $codes = array_values(array_unique($codes));
        }
        TenantSetting::setForTenant($tid, 'money.additional_currency_codes', $codes, 'json');

        $tenant->currency = $base;
        $tenant->save();

        unset(
            $data['money_base_currency_code'],
            $data['money_fraction_display_mode'],
            $data['money_display_scale_exponent'],
            $data['money_display_unit_label_override'],
            $data['money_multi_currency_enabled'],
            $data['money_additional_currency_codes'],
        );
    }

    private function resolvedGeneralDomainFormValue(Tenant $tenant): string
    {
        $stored = trim((string) TenantSetting::getForTenant($tenant->id, 'general.domain', ''));
        if ($stored !== '' && filter_var($stored, FILTER_VALIDATE_URL)) {
            return rtrim($stored, '/');
        }

        return rtrim($tenant->defaultPublicSiteUrl(), '/');
    }

    private function assertBrandingUploadsWithinQuota(Tenant $tenant, array $formData): void
    {
        if (! TenantStorageQuotaService::isQuotaEnforcementActive()) {
            return;
        }

        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $before = $this->getSettingsData();
        $fields = ['branding_logo_path', 'branding_favicon_path', 'branding_hero_path'];
        $sum = 0;
        foreach ($fields as $field) {
            $new = isset($formData[$field]) ? (string) $formData[$field] : '';
            $old = isset($before[$field]) ? (string) $before[$field] : '';
            if ($new === '' || $new === $old) {
                continue;
            }
            if (! $disk->exists($new)) {
                continue;
            }
            $sum += (int) $disk->size($new);
        }
        if ($sum > 0) {
            app(TenantStorageQuotaService::class)->assertCanStoreBytes($tenant, $sum, 'branding_upload');
        }
    }

    /**
     * Публичные кнопки используют тёмную типографику на акцентном фоне — слишком тёмный бренд-цвет даёт провал по контрасту (WCAG AA).
     */
    private function notifyIfBrandingPrimaryLowContrast(?string $hex): void
    {
        if ($hex === null || trim($hex) === '') {
            return;
        }

        $ratio = WcagContrast::ratio(trim($hex), '#0c0c0c');
        if ($ratio !== null && $ratio < 4.5) {
            Notification::make()
                ->title('Контраст основного цвета')
                ->warning()
                ->body('Выбранный цвет может быть слишком тёмным для тёмного текста на кнопках публичного сайта (ориентир WCAG 4.5:1). Рекомендуется более светлый или насыщенный акцент.')
                ->send();
        }
    }
}
