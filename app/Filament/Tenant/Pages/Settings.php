<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Setting;
use App\Models\TenantSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class Settings extends Page
{
    /**
     * Form state keys (underscore) → dotted keys passed to TenantSetting / Setting (must match getSettingsData()).
     *
     * @var array<string, string>
     */
    private const FORM_FIELD_TO_SETTING_KEY = [
        'general_site_name' => 'general.site_name',
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
        'seo_robots_txt' => 'seo.robots_txt',
    ];

    protected static ?string $navigationLabel = 'Настройки';

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
        $this->data = $this->getSettingsData();
    }

    protected function getSettingsData(): array
    {
        $tenant = \currentTenant();
        if ($tenant) {
            return [
                'general_site_name' => TenantSetting::getForTenant($tenant->id, 'general.site_name', $tenant->defaultPublicSiteName()),
                'general_domain' => TenantSetting::getForTenant($tenant->id, 'general.domain', $tenant->defaultPublicSiteUrl()),
                'branding_logo' => TenantSetting::getForTenant($tenant->id, 'branding.logo', ''),
                'branding_logo_path' => TenantSetting::getForTenant($tenant->id, 'branding.logo_path', ''),
                'branding_primary_color' => TenantSetting::getForTenant($tenant->id, 'branding.primary_color', '#f59e0b'),
                'branding_favicon' => TenantSetting::getForTenant($tenant->id, 'branding.favicon', ''),
                'branding_favicon_path' => TenantSetting::getForTenant($tenant->id, 'branding.favicon_path', ''),
                'branding_hero' => TenantSetting::getForTenant($tenant->id, 'branding.hero', ''),
                'branding_hero_path' => TenantSetting::getForTenant($tenant->id, 'branding.hero_path', ''),
                'contacts_phone' => TenantSetting::getForTenant($tenant->id, 'contacts.phone', ''),
                'contacts_phone_alt' => TenantSetting::getForTenant($tenant->id, 'contacts.phone_alt', ''),
                'contacts_email' => TenantSetting::getForTenant($tenant->id, 'contacts.email', ''),
                'contacts_whatsapp' => TenantSetting::getForTenant($tenant->id, 'contacts.whatsapp', ''),
                'contacts_telegram' => TenantSetting::getForTenant($tenant->id, 'contacts.telegram', ''),
                'contacts_address' => TenantSetting::getForTenant($tenant->id, 'contacts.address', ''),
                'contacts_hours' => TenantSetting::getForTenant($tenant->id, 'contacts.hours', ''),
                'seo_robots_txt' => TenantSetting::getForTenant($tenant->id, 'seo.robots_txt', ''),
            ];
        }

        return [
            'general_site_name' => Setting::get('general.site_name', config('app.name')),
            'general_domain' => Setting::get('general.domain', config('app.url')),
            'branding_logo' => '',
            'branding_primary_color' => '#f59e0b',
            'branding_favicon' => '',
            'contacts_phone' => Setting::get('contacts.phone', ''),
            'contacts_phone_alt' => Setting::get('contacts.phone_alt', ''),
            'contacts_email' => Setting::get('contacts.email', ''),
            'contacts_whatsapp' => Setting::get('contacts.whatsapp', ''),
            'contacts_telegram' => Setting::get('contacts.telegram', ''),
            'contacts_address' => Setting::get('contacts.address', ''),
            'contacts_hours' => Setting::get('contacts.hours', ''),
            'seo_robots_txt' => Setting::get('seo.robots_txt', ''),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Общие')
                    ->description('Базовая информация о сайте для посетителей. Пока поля пустые в БД, подставляются название/бренд клиента и домен (текущий активный или основной), а не настройки лендинга платформы.')
                    ->schema([
                        TextInput::make('general_site_name')
                            ->label('Название сайта')
                            ->helperText('Показывается в шапке, заголовках и письмах, если тема не задаёт иначе.')
                            ->placeholder('Например: MotoLevins Сочи'),
                        TextInput::make('general_domain')
                            ->label('Основной URL сайта')
                            ->url()
                            ->helperText('Канонический адрес сайта клиента (https://…). По умолчанию берётся из домена, с которого открыта админка, или основного домена в карточке клиента.'),
                    ])->columns(2),

                Section::make('Брендинг')
                    ->description('Файлы сохраняются в storage (путь привязан к ID клиента). URL-поля — для внешних ссылок; если загружен файл, он имеет приоритет.')
                    ->schema([
                        FileUpload::make('branding_logo_path')
                            ->label('Логотип (файл)')
                            ->disk('public')
                            ->directory(function (): string {
                                $t = \currentTenant();
                                abort_if($t === null, 403);

                                return 'tenants/'.$t->id.'/logo';
                            })
                            ->image()
                            ->maxSize(2048)
                            ->nullable()
                            ->helperText('PNG, JPG, WebP. До 2 МБ.'),
                        TextInput::make('branding_logo')
                            ->label('URL логотипа (legacy)')
                            ->url()
                            ->placeholder('https://...')
                            ->helperText('Используется, если файл не загружен.'),
                        TextInput::make('branding_primary_color')
                            ->label('Основной цвет')
                            ->type('color')
                            ->helperText('Акцентные кнопки и ссылки на сайте. Рядом — текущий выбранный цвет (стандартный виджет браузера).'),
                        FileUpload::make('branding_favicon_path')
                            ->label('Favicon (файл)')
                            ->disk('public')
                            ->directory(function (): string {
                                $t = \currentTenant();
                                abort_if($t === null, 403);

                                return 'tenants/'.$t->id.'/favicon';
                            })
                            ->maxSize(512)
                            ->nullable()
                            ->helperText('PNG, ICO, JPG до 512 КБ.'),
                        TextInput::make('branding_favicon')
                            ->label('URL favicon (legacy)')
                            ->url()
                            ->placeholder('https://...')
                            ->helperText('Используется, если файл не загружен.'),
                        FileUpload::make('branding_hero_path')
                            ->label('Hero / OG-изображение (файл)')
                            ->disk('public')
                            ->directory(function (): string {
                                $t = \currentTenant();
                                abort_if($t === null, 403);

                                return 'tenants/'.$t->id.'/hero';
                            })
                            ->image()
                            ->maxSize(4096)
                            ->nullable()
                            ->helperText('Крупное изображение для шапки или соцсетей; вывод задаётся темой.'),
                        TextInput::make('branding_hero')
                            ->label('URL hero (legacy)')
                            ->url()
                            ->nullable()
                            ->helperText('Внешняя ссылка, если файл не загружен.'),
                    ])->columns(2)->visible(fn () => \currentTenant() !== null),

                Section::make('Контакты')
                    ->description('Телефоны и мессенджеры обычно выводятся в шапке, подвале и на странице контактов.')
                    ->schema([
                        TextInput::make('contacts_phone')->label('Телефон')->tel()->placeholder('+7 …'),
                        TextInput::make('contacts_phone_alt')->label('Дополнительный телефон')->tel(),
                        TextInput::make('contacts_email')->label('Email')->email()->placeholder('hello@example.com'),
                        TextInput::make('contacts_whatsapp')->label('WhatsApp')->tel()->placeholder('Только номер или ссылка wa.me'),
                        TextInput::make('contacts_telegram')->label('Telegram')->placeholder('@username или ссылка t.me/…'),
                        Textarea::make('contacts_address')->label('Адрес')->rows(2),
                        Textarea::make('contacts_hours')->label('Часы работы')->rows(2)->placeholder('Например: Пн–Вс 9:00–21:00'),
                    ])->columns(2),

                Section::make('SEO')
                    ->description('Файл robots.txt сообщает поисковикам, что можно индексировать. Если оставить пустым, платформа может сформировать его автоматически.')
                    ->schema([
                        Textarea::make('seo_robots_txt')
                            ->label('Содержимое robots.txt')
                            ->rows(10)
                            ->placeholder("User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: https://ваш-сайт/sitemap.xml"),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->getSchema('form')->getState();
        $tenant = \currentTenant();

        foreach ($data as $field => $value) {
            if (! array_key_exists($field, self::FORM_FIELD_TO_SETTING_KEY)) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $settingKey = self::FORM_FIELD_TO_SETTING_KEY[$field];
            $stored = $value === null ? '' : (string) $value;

            if ($tenant) {
                TenantSetting::setForTenant($tenant->id, $settingKey, $stored);
            } else {
                Setting::set($settingKey, $stored);
            }
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
}
