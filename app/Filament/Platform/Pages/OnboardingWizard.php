<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use App\Filament\Platform\Resources\TenantResource;
use App\Models\Plan;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Services\TemplateCloningService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OnboardingWizard extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.onboarding-wizard';

    protected static ?string $title = 'Создание клиента';

    protected static ?string $navigationLabel = 'Новый клиент (Wizard)';

    protected static ?string $slug = 'onboarding';

    protected static ?string $panel = 'platform';

    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('form')->fill([
            'timezone' => 'Europe/Moscow',
            'locale' => 'ru',
            'currency' => 'RUB',
            'primary_color' => '#E85D04',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Onboarding')
                    ->tabs([
                        Tab::make('1. Клиент')
                            ->schema([
                                Section::make('Основные данные')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Название компании')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                        TextInput::make('slug')
                                            ->label('URL-идентификатор')
                                            ->required()
                                            ->unique('tenants', 'slug'),
                                    ])->columns(2),
                            ]),
                        Tab::make('2. Шаблон')
                            ->schema([
                                Section::make('Выберите шаблон сайта')
                                    ->schema([
                                        Select::make('template_preset_id')
                                            ->label('Шаблон')
                                            ->options(TemplatePreset::where('is_active', true)->pluck('name', 'id'))
                                            ->required()
                                            ->searchable(),
                                    ]),
                            ]),
                        Tab::make('3. Брендинг')
                            ->schema([
                                Section::make('Внешний вид')
                                    ->schema([
                                        TextInput::make('brand_name')
                                            ->label('Бренд / название для сайта'),
                                        TextInput::make('logo_url')
                                            ->label('URL логотипа')
                                            ->url(),
                                        TextInput::make('primary_color')
                                            ->label('Основной цвет')
                                            ->default('#E85D04'),
                                    ])->columns(2),
                            ]),
                        Tab::make('4. Контакты')
                            ->schema([
                                Section::make('Контактная информация')
                                    ->schema([
                                        TextInput::make('contact_phone')
                                            ->label('Телефон'),
                                        TextInput::make('contact_email')
                                            ->label('Email')
                                            ->email(),
                                        TextInput::make('contact_telegram')
                                            ->label('Telegram'),
                                        TextInput::make('contact_whatsapp')
                                            ->label('WhatsApp'),
                                    ])->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function create(): void
    {
        $data = $this->getSchema('form')->getState();

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'brand_name' => $data['brand_name'] ?? null,
            'status' => 'trial',
            'plan_id' => Plan::first()?->id,
            'timezone' => $data['timezone'] ?? 'Europe/Moscow',
            'locale' => $data['locale'] ?? 'ru',
            'currency' => $data['currency'] ?? 'RUB',
        ]);

        $preset = TemplatePreset::find($data['template_preset_id'] ?? null);
        if ($preset) {
            app(TemplateCloningService::class)->cloneToTenant($tenant, $preset);
        }

        $host = $tenant->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST) ?: $tenant->slug.'.localhost';
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'host' => $host,
            'type' => 'subdomain',
            'is_primary' => true,
            'verification_status' => 'verified',
        ]);

        if (! empty($data['logo_url'])) {
            TenantSetting::setForTenant($tenant->id, 'branding.logo', $data['logo_url']);
        }
        if (! empty($data['primary_color'])) {
            TenantSetting::setForTenant($tenant->id, 'branding.primary_color', $data['primary_color']);
        }
        if (! empty($data['contact_phone'])) {
            TenantSetting::setForTenant($tenant->id, 'contacts.phone', $data['contact_phone']);
        }
        if (! empty($data['contact_email'])) {
            TenantSetting::setForTenant($tenant->id, 'contacts.email', $data['contact_email']);
        }
        if (! empty($data['contact_telegram'])) {
            TenantSetting::setForTenant($tenant->id, 'contacts.telegram', $data['contact_telegram']);
        }
        if (! empty($data['contact_whatsapp'])) {
            TenantSetting::setForTenant($tenant->id, 'contacts.whatsapp', $data['contact_whatsapp']);
        }
        if (! empty($data['brand_name'])) {
            TenantSetting::setForTenant($tenant->id, 'general.site_name', $data['brand_name']);
        }

        $this->redirect(TenantResource::getUrl('edit', ['record' => $tenant]));
    }
}
