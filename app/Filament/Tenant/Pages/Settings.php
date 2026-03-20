<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Setting;
use App\Models\TenantSetting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class Settings extends Page
{
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
                'general_site_name' => TenantSetting::getForTenant($tenant->id, 'general.site_name', config('app.name')),
                'general_domain' => TenantSetting::getForTenant($tenant->id, 'general.domain', config('app.url')),
                'branding_logo' => TenantSetting::getForTenant($tenant->id, 'branding.logo', ''),
                'branding_primary_color' => TenantSetting::getForTenant($tenant->id, 'branding.primary_color', '#f59e0b'),
                'branding_favicon' => TenantSetting::getForTenant($tenant->id, 'branding.favicon', ''),
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
                    ->description('Базовая информация о сайте для посетителей и для служебных ссылок.')
                    ->schema([
                        TextInput::make('general_site_name')
                            ->label('Название сайта')
                            ->helperText('Показывается в шапке, заголовках и письмах, если тема не задаёт иначе.')
                            ->placeholder('Например: MotoLevins Сочи'),
                        TextInput::make('general_domain')
                            ->label('Основной URL сайта')
                            ->url()
                            ->helperText('Полный адрес с https://. Используется в ссылках и настройках, где нужен «канонический» домен.'),
                    ])->columns(2),

                Section::make('Брендинг')
                    ->description('Логотип и цвета влияют на оформление **сайта для посетителей**. Полноэкранный предпросмотр шапки можно добавить позже; сейчас проверяйте результат на опубликованном сайте.')
                    ->schema([
                        TextInput::make('branding_logo')
                            ->label('URL логотипа')
                            ->url()
                            ->placeholder('https://...')
                            ->helperText('Прямая ссылка на файл изображения (PNG/SVG). Лучше горизонтальный логотип на прозрачном фоне.'),
                        TextInput::make('branding_primary_color')
                            ->label('Основной цвет')
                            ->type('color')
                            ->helperText('Акцентные кнопки и ссылки на сайте. Рядом — текущий выбранный цвет (стандартный виджет браузера).'),
                        TextInput::make('branding_favicon')
                            ->label('URL иконки сайта (favicon)')
                            ->url()
                            ->placeholder('https://...')
                            ->helperText('Маленькая иконка во вкладке браузера; обычно 32×32 или .ico.'),
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

        foreach ($data as $key => $value) {
            $parts = explode('_', $key, 2);
            $group = $parts[0] ?? 'general';
            $k = str_replace('_', '.', $parts[1] ?? $key);
            $settingKey = "{$group}.{$k}";
            if ($tenant) {
                TenantSetting::setForTenant($tenant->id, $settingKey, $value);
            } else {
                Setting::set($settingKey, $value);
            }
        }

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
