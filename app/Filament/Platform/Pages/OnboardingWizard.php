<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use App\Filament\Platform\TenantPlanCreationNotifications;
use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Shared\TimezoneSelect;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Models\DomainLocalizationPreset;
use App\Models\Plan;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantProvisioningService;
use App\Rules\OptionalRussianPhone;
use App\Scheduling\SchedulingTimezoneOptions;
use App\Support\RussianPhone;
use App\Support\TenantRegionalContract;
use App\Support\TenantSlug;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use UnitEnum;

class OnboardingWizard extends Page
{
    use GrantsPlatformPageAccess;

    protected string $view = 'filament.pages.platform.onboarding-wizard';

    protected static ?string $title = 'Мастер: новый клиент';

    protected static ?string $navigationLabel = 'Новый клиент (мастер)';

    /** Рядом со списком «Клиенты» в сайдбаре консоли платформы. */
    protected static string|UnitEnum|null $navigationGroup = 'Клиенты';

    protected static ?string $slug = 'onboarding';

    protected static ?string $panel = 'platform';

    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('form')->fill([
            'timezone' => SchedulingTimezoneOptions::DEFAULT_IDENTIFIER,
            'locale' => 'ru',
            'currency' => 'RUB',
            'primary_color' => '#E85D04',
            'domain_localization_preset_id' => DomainLocalizationPreset::query()->where('slug', 'generic_services')->value('id'),
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
                                Section::make('Компания и адрес в системе')
                                    ->description(FilamentInlineMarkdown::toHtml(
                                        'Создаётся запись **клиента платформы** (отдельный сайт и данные). '.
                                        '**Можно изменить позже:** название, URL-идентификатор, часовой пояс, язык и валюта — в карточке клиента в разделе «Клиенты».'
                                    ))
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Название компании или проекта')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', TenantSlug::normalize(Str::slug((string) $state))))
                                            ->placeholder('Например: MotoRent Сочи'),
                                        TextInput::make('slug')
                                            ->label('URL-идентификатор')
                                            ->required()
                                            ->rules([
                                                function () {
                                                    return function (string $attribute, mixed $value, \Closure $fail): void {
                                                        $n = TenantSlug::normalize((string) $value);
                                                        if (! TenantSlug::isValidProductSlug($n)) {
                                                            $fail('Идентификатор: латиница, цифры и одиночные дефисы между фрагментами (без дефиса в начале или конце).');
                                                        }
                                                    };
                                                },
                                            ])
                                            ->helperText('Используется в адресе технического поддомена. Латиница, цифры и дефис.'),
                                        TimezoneSelect::make('timezone')
                                            ->default(SchedulingTimezoneOptions::DEFAULT_IDENTIFIER),
                                        TextInput::make('locale')
                                            ->label('Локаль')
                                            ->required()
                                            ->default('ru')
                                            ->helperText('Обязательно: код локали (например ru или en-US).'),
                                        TextInput::make('currency')
                                            ->label('Валюта')
                                            ->required()
                                            ->default('RUB')
                                            ->helperText('Обязательно: трёхбуквенный код ISO 4217 (например RUB).'),
                                        Select::make('domain_localization_preset_id')
                                            ->label('Тематика терминологии')
                                            ->options(
                                                DomainLocalizationPreset::query()
                                                    ->where('is_active', true)
                                                    ->orderBy('sort_order')
                                                    ->pluck('name', 'id')
                                            )
                                            ->preload()
                                            ->helperText('Подписи в кабинете клиента. Отдельно от темы публичного сайта.'),
                                    ])->columns(2),
                            ]),
                        Tab::make('2. Шаблон')
                            ->schema([
                                Section::make('Стартовый сайт')
                                    ->description(FilamentInlineMarkdown::toHtml(
                                        'Из шаблона копируются страницы и настройки **как черновик**. Уже созданные сайты клиентов от шаблона не зависят — правки шаблона задним числом их не меняют. '.
                                        '**Можно изменить позже:** контент и структуру можно править в кабинете клиента; другой шаблон «переключить» задним числом нельзя — только вручную переносить контент.'
                                    ))
                                    ->schema([
                                        Select::make('template_preset_id')
                                            ->label('Шаблон сайта')
                                            ->options(TemplatePreset::where('is_active', true)->pluck('name', 'id'))
                                            ->required()
                                            ->searchable()
                                            ->helperText('Выберите активный шаблон из списка.'),
                                    ]),
                            ]),
                        Tab::make('3. Брендинг')
                            ->schema([
                                Section::make('Внешний вид на сайте')
                                    ->description(FilamentInlineMarkdown::toHtml(
                                        'Эти данные попадут в настройки сайта клиента и обычно видны посетителям. '.
                                        '**Можно изменить позже:** всё в кабинете клиента → «Настройки сайта» (брендинг).'
                                    ))
                                    ->schema([
                                        TextInput::make('brand_name')
                                            ->label('Название на сайте')
                                            ->placeholder('Как показывать бренд посетителям'),
                                        TextInput::make('logo_url')
                                            ->label('URL логотипа')
                                            ->url()
                                            ->placeholder('https://…'),
                                        TextInput::make('primary_color')
                                            ->label('Основной цвет')
                                            ->type('color')
                                            ->default('#E85D04')
                                            ->helperText('Акцентные элементы на публичном сайте.'),
                                    ])->columns(2),
                            ]),
                        Tab::make('4. Контакты')
                            ->schema([
                                Section::make('Связь с клиентом')
                                    ->description(FilamentInlineMarkdown::toHtml(
                                        'Телефон и мессенджеры обычно выводятся в шапке и на странице контактов. '.
                                        '**Можно изменить позже:** раздел «Настройки сайта» → контакты.'
                                    ))
                                    ->schema([
                                        TextInput::make('contact_phone')
                                            ->label('Телефон')
                                            ->tel()
                                            ->telRegex(RussianPhone::filamentTelDisplayRegex())
                                            ->rules([new OptionalRussianPhone]),
                                        TextInput::make('contact_email')
                                            ->label('Email')
                                            ->email(),
                                        TextInput::make('contact_telegram')
                                            ->label('Telegram'),
                                        TextInput::make('contact_whatsapp')
                                            ->label('WhatsApp'),
                                    ])->columns(2),
                            ]),
                        Tab::make('5. Транспорт')
                            ->schema([
                                Section::make('Каталог техники')
                                    ->description(FilamentInlineMarkdown::toHtml(
                                        'На этом шаге в будущем можно будет добавить первые **карточки в каталоге** или импорт. '.
                                        'Сейчас данные вносятся в **кабинете клиента** после создания. '.
                                        '**Можно изменить позже:** полностью в кабинете клиента → каталог.'
                                    )),
                            ]),
                        Tab::make('6. Публикация')
                            ->schema([
                                Section::make('Запуск сайта')
                                    ->description(FilamentInlineMarkdown::toHtml(
                                        'Здесь позже появится чеклист: домен, SSL, опубликованные страницы. '.
                                        'Сейчас после создания клиента откроется карточка клиента — назначьте тариф, проверьте домен и передайте доступ в кабинет. '.
                                        '**Можно изменить позже:** статус публикации и домены — в консоли платформы (клиент, домены).'
                                    )),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function create(): void
    {
        $data = $this->getSchema('form')->getState();

        $defaultPlanId = Plan::defaultIdForOnboarding();
        if ($defaultPlanId === null) {
            TenantPlanCreationNotifications::noActivePlans()->send();

            return;
        }

        $templatePresetId = isset($data['template_preset_id']) ? (int) $data['template_preset_id'] : null;
        $templatePresetId = ($templatePresetId !== null && $templatePresetId > 0) ? $templatePresetId : null;

        $slug = TenantSlug::normalize((string) ($data['slug'] ?? ''));
        if (! TenantSlug::isValidProductSlug($slug)) {
            Notification::make()
                ->title('Проверьте URL-идентификатор')
                ->body('Допустимы латиница, цифры и одиночные дефисы между фрагментами.')
                ->danger()
                ->send();

            return;
        }

        if (TenantSlug::isNormalizedSlugTaken($slug)) {
            Notification::make()
                ->title('Такой URL-идентификатор уже занят')
                ->body('Выберите другой идентификатор (после приведения к нижнему регистру он должен быть уникален).')
                ->danger()
                ->send();

            return;
        }

        $validator = Validator::make($data, [
            'contact_phone' => ['nullable', new OptionalRussianPhone],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                Notification::make()->title($message)->danger()->send();
            }

            return;
        }

        $locale = TenantRegionalContract::normalizeLocale($data['locale'] ?? null);
        $currency = TenantRegionalContract::normalizeCurrency($data['currency'] ?? null);
        if ($locale === null || ! TenantRegionalContract::isValidLocale($locale)) {
            Notification::make()
                ->title('Проверьте локаль')
                ->body('Например: ru, en, en-US.')
                ->danger()
                ->send();

            return;
        }
        if ($currency === null || ! TenantRegionalContract::isValidCurrency($currency)) {
            Notification::make()
                ->title('Проверьте валюту')
                ->body('Нужен трёхбуквенный код ISO 4217, например RUB.')
                ->danger()
                ->send();

            return;
        }

        $tenant = DB::transaction(function () use ($data, $defaultPlanId, $slug, $locale, $currency) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $slug,
                'brand_name' => $data['brand_name'] ?? null,
                'status' => 'trial',
                'plan_id' => $defaultPlanId,
                'timezone' => $data['timezone'] ?? SchedulingTimezoneOptions::DEFAULT_IDENTIFIER,
                'locale' => $locale,
                'currency' => $currency,
                'domain_localization_preset_id' => $data['domain_localization_preset_id']
                    ?? DomainLocalizationPreset::query()->where('slug', 'generic_services')->value('id'),
            ]);

            if (! empty($data['logo_url'])) {
                TenantSetting::setForTenant($tenant->id, 'branding.logo', $data['logo_url']);
            }
            if (! empty($data['primary_color'])) {
                TenantSetting::setForTenant($tenant->id, 'branding.primary_color', $data['primary_color']);
            }
            $phoneNormalized = RussianPhone::normalize($data['contact_phone'] ?? null);
            if ($phoneNormalized !== null && $phoneNormalized !== '') {
                TenantSetting::setForTenant($tenant->id, 'contacts.phone', $phoneNormalized);
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

            return $tenant;
        });

        DB::afterCommit(function () use ($tenant, $templatePresetId): void {
            try {
                app(TenantProvisioningService::class)->bootstrapAfterTenantCreated($tenant, $templatePresetId);
            } catch (\Throwable $e) {
                Log::error('tenant_provisioning_failed_after_onboarding', [
                    'tenant_id' => $tenant->id,
                    'exception' => $e,
                ]);
                throw $e;
            }
        });

        $this->redirect(TenantResource::getUrl('edit', ['record' => $tenant]));
    }
}
