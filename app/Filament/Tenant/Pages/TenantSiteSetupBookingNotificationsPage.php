<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Concerns\ResolvesTenantOnboardingBranch;
use App\Models\Tenant;
use App\NotificationCenter\NotificationEventRegistry;
use App\Filament\Shared\TimezoneSelect;
use App\Validation\TelegramBriefChatIdRule;
use App\TenantSiteSetup\BookingNotificationsBriefingApplier;
use App\TenantSiteSetup\BookingNotificationsQuestionnaireRepository;
use App\TenantSiteSetup\SetupProductSignalsRepository;
use App\TenantSiteSetup\TenantSiteSetupFeature;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use UnitEnum;

class TenantSiteSetupBookingNotificationsPage extends Page
{
    use ResolvesTenantOnboardingBranch;

    protected static string|UnitEnum|null $navigationGroup = 'SiteLaunch';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Запись и уведомления (бриф)';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $title = 'Запись и уведомления';

    protected static ?string $slug = 'site-setup-booking-notifications';

    protected string $view = 'filament.tenant.pages.tenant-site-setup-booking-notifications';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        if (! TenantSiteSetupFeature::enabled()) {
            return false;
        }

        if (! Gate::allows('manage_settings') || currentTenant() === null) {
            return false;
        }

        $u = Auth::user();

        return $u !== null && (
            Gate::forUser($u)->allows('manage_scheduling')
            || Gate::forUser($u)->allows('manage_notifications')
            || Gate::forUser($u)->allows('manage_notification_destinations')
            || Gate::forUser($u)->allows('manage_notification_subscriptions')
        );
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $this->data = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);
        $this->hydrateProductCalendarForm((int) $tenant->id);
        $this->data = $this->stripDisallowedBookingEventsFromQuestionnaireState($tenant, $this->data);
    }

    public function form(Schema $schema): Schema
    {
        $tenant = currentTenant();
        $schedulingOn = $tenant !== null && $tenant->scheduling_module_enabled;

        $eventOptions = [];
        foreach (NotificationEventRegistry::all() as $def) {
            if (str_starts_with($def->key, 'booking.')
                && (! $schedulingOn || $this->branchResolution->shouldFilterBookingNotificationEvents())) {
                continue;
            }
            $eventOptions[$def->key] = $def->defaultTitle.' ('.$def->key.')';
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Согласованность сценария')
                    ->description('Сверка выбранной ветки онбординга (профиль запуска) с модулем записи и правами. На то, что реально применится из этой анкеты, влияет фактическая ветка.')
                    ->schema([
                        ViewField::make('onboarding_branch_alert')
                            ->hiddenLabel()
                            ->view('filament.tenant.forms.onboarding-branch-alert')
                            ->viewData(fn (): array => [
                                'resolution' => $this->branchResolution,
                            ]),
                    ]),
                Section::make('Контекст')
                    ->description('Бриф для автоматической настройки групп записи, получателей и правил уведомлений. Подробный перечень вопросов — в документации для гида.')
                    ->schema([
                        TextInput::make('meta_brand_name')
                            ->label('Бренд / название на сайте')
                            ->maxLength(255),
                        TimezoneSelect::make('meta_timezone'),
                    ])
                    ->columns(2),
                Section::make('Календари (сигнал для гида)')
                    ->description('Сохраняется в продуктовые сигналы запуска; не подключает синхронизацию и не меняет боевые настройки сайта.')
                    ->schema([
                        Select::make('product_cal_uses_external')
                            ->label('Используете внешние календари')
                            ->helperText('«Нет» — провайдеры и заметки скрыты. «Не указано» — показываем все поля, чтобы было видно, что можно заполнить.')
                            ->options([
                                '' => 'Не указано',
                                '1' => 'Да',
                                '0' => 'Нет',
                            ])
                            ->native(true)
                            ->live(),
                        CheckboxList::make('product_cal_providers')
                            ->label('Провайдеры')
                            ->visible(fn (Get $get): bool => $this->productCalSubfieldsVisible($get))
                            ->options([
                                'google' => 'Google Calendar',
                                'yandex' => 'Яндекс.Календарь',
                                'outlook' => 'Microsoft Outlook / 365',
                                'apple_icloud' => 'Apple iCloud',
                                'caldav' => 'CalDAV',
                                'other' => 'Другое',
                            ])
                            ->columns(2)
                            ->live(),
                        TextInput::make('product_cal_other_text')
                            ->label('Уточнение, если выбрано «Другое»')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $this->productCalOtherClarifyVisible($get)),
                        Textarea::make('product_cal_notes')
                            ->label('Заметки')
                            ->rows(3)
                            ->visible(fn (Get $get): bool => $this->productCalSubfieldsVisible($get)),
                    ]),
                Section::make('Параметры записи (пресет)')
                    ->description('Используется только при фактической ветке с онлайн-записью: модуль записи, ваши права и согласованный сценарий в профиле запуска.')
                    ->visible(fn (): bool => $schedulingOn
                        && Gate::allows('manage_scheduling')
                        && ! $this->branchResolution->shouldSuppressBookingAutomation())
                    ->schema([
                        TextInput::make('sched_duration_min')
                            ->label('Длительность слота (мин)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(24 * 60),
                        TextInput::make('sched_slot_step_min')
                            ->label('Шаг между слотами (мин)')
                            ->numeric()
                            ->minValue(5),
                        TextInput::make('sched_buffer_before')
                            ->label('Буфер до (мин)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('sched_buffer_after')
                            ->label('Буфер после (мин)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('sched_horizon_days')
                            ->label('Запись не дальше (дней)')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('sched_notice_min')
                            ->label('Минимум времени до начала (мин)')
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('sched_requires_confirmation')
                            ->label('Подтверждать заявку вручную')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Куда слать уведомления')
                    ->description($this->recipientsSectionDescription())
                    ->schema([
                        TextInput::make('dest_email')
                            ->label('Email (один адрес)')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('name@example.com')
                            ->helperText(
                                'Здесь указывается один почтовый ящик; дополнительные адреса можно добавить позже в «Получатели уведомлений». '
                                .'На этот адрес платформа отправит копии уведомлений (проверьте «Входящие» и спам при первой отправке).'
                            ),
                        TextInput::make('dest_telegram_chat_id')
                            ->label('Telegram (один chat_id или @канал)')
                            ->placeholder('-1001234567890')
                            ->maxLength(128)
                            ->helperText($this->destTelegramHelperHtml())
                            ->rules([new TelegramBriefChatIdRule()]),
                    ])
                    ->columns(2),
                Section::make('События для правил')
                    ->description('Для отмеченных событий будут созданы или обновлены правила с доставкой на указанные получатели.')
                    ->schema([
                        CheckboxList::make('events_enabled')
                            ->label('События')
                            ->options($eventOptions)
                            ->columns(1)
                            ->bulkToggleable(),
                    ]),
                ViewField::make('tenant_site_setup_booking_brief_guided_anchor')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->view('filament.tenant.forms.site-setup-booking-brief-guided-anchor'),
            ]);
    }

    public function saveDraft(): void
    {
        abort_unless(static::canAccess(), 403);
        $tenant = currentTenant();
        if ($tenant === null) {
            return;
        }

        $state = $this->mergeFormStateWithProductCalendarFields($this->getSchema('form')->getState());
        $this->persistProductCalendarSignals((int) $tenant->id, $state);
        $state = $this->stripProductCalendarFormKeys($state);
        $state = $this->stripDisallowedBookingEventsFromQuestionnaireState($tenant, $state);
        app(BookingNotificationsQuestionnaireRepository::class)->save($tenant->id, $state);

        Notification::make()
            ->title('Черновик сохранён')
            ->success()
            ->send();
    }

    public function applyNow(): void
    {
        abort_unless(static::canAccess(), 403);
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null) {
            return;
        }

        $applier = app(BookingNotificationsBriefingApplier::class);
        $applier->assertCanApplySomething($user);

        $state = $this->mergeFormStateWithProductCalendarFields($this->getSchema('form')->getState());
        $this->persistProductCalendarSignals((int) $tenant->id, $state);
        $state = $this->stripProductCalendarFormKeys($state);
        $state = $this->stripDisallowedBookingEventsFromQuestionnaireState($tenant, $state);
        $result = $applier->apply($tenant, $user, $state);

        Notification::make()
            ->title('Настройки применены')
            ->body(
                'Получателей: '.$result['destinations_created'].', правил: '.$result['subscriptions_created']
                .($result['preset_id'] !== null ? ', пресет #'.$result['preset_id'] : '')
            )
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveDraft')
                ->label('Сохранить черновик')
                ->action('saveDraft'),
            Action::make('applyNow')
                ->label('Применить к кабинету')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Применить настройки из анкеты к кабинету?')
                ->modalDescription('Будут созданы или обновлены пресет записи (если онлайн-запись доступна по модулю, вашим правам и выбранному сценарию в профиле запуска), получатели и правила уведомлений по выбранным событиям.')
                ->action('applyNow'),
        ];
    }

    /*
     * Product signals (product_cal_* ↔ calendar_signals): пока держим в странице; при росте блока
     * логично вынести сопоставление полей в маленький DTO/mapper рядом с {@see SetupProductSignalsRepository}.
     */

    /**
     * Поля product_cal_* привязаны к statePath `data`, но {@see Schema::getState()} может не вернуть их целиком — подмешиваем из Livewire state.
     *
     * @param  array<string, mixed>  $fromForm
     * @return array<string, mixed>
     */
    private function mergeFormStateWithProductCalendarFields(array $fromForm): array
    {
        $cal = [];
        foreach ($this->data ?? [] as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'product_cal_')) {
                $cal[$key] = $value;
            }
        }
        foreach ($fromForm as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'product_cal_') && ! array_key_exists($key, $cal)) {
                $cal[$key] = $value;
            }
        }

        return array_merge($fromForm, $cal);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persistProductCalendarSignals(int $tenantId, array $state): void
    {
        $repo = app(SetupProductSignalsRepository::class);
        $merged = $repo->getMerged($tenantId);
        $raw = $state['product_cal_uses_external'] ?? '';
        $usesExternal = match (true) {
            $raw === '1' || $raw === true => true,
            $raw === '0' || $raw === false => false,
            default => null,
        };
        if ($usesExternal === false) {
            $merged['calendar_signals'] = array_merge($merged['calendar_signals'], [
                'uses_external_calendars' => false,
                'providers' => [],
                'other_provider_text' => '',
                'notes' => '',
            ]);
            $repo->save($tenantId, $merged);

            return;
        }

        $providers = array_values(array_filter(array_map('strval', (array) ($state['product_cal_providers'] ?? []))));
        $hasOther = in_array('other', $providers, true);
        $otherText = $hasOther ? trim((string) ($state['product_cal_other_text'] ?? '')) : '';
        $notes = trim((string) ($state['product_cal_notes'] ?? ''));

        $merged['calendar_signals'] = array_merge($merged['calendar_signals'], [
            'uses_external_calendars' => $usesExternal,
            'providers' => $providers,
            'other_provider_text' => $otherText,
            'notes' => $notes,
        ]);
        $repo->save($tenantId, $merged);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function stripProductCalendarFormKeys(array $state): array
    {
        foreach (array_keys($state) as $k) {
            if (is_string($k) && str_starts_with($k, 'product_cal_')) {
                unset($state[$k]);
            }
        }

        return $state;
    }

    private function hydrateProductCalendarForm(int $tenantId): void
    {
        $cal = app(SetupProductSignalsRepository::class)->getMerged($tenantId)['calendar_signals'];
        $uses = $cal['uses_external_calendars'] ?? null;
        $this->data['product_cal_uses_external'] = $uses === null ? '' : ($uses ? '1' : '0');
        $this->data['product_cal_providers'] = $cal['providers'] ?? [];
        $this->data['product_cal_other_text'] = (string) ($cal['other_provider_text'] ?? '');
        $this->data['product_cal_notes'] = (string) ($cal['notes'] ?? '');
    }

    /**
     * События booking.* не сохраняем в черновик, если они недоступны в UI (модуль выключен или фактическая ветка CRM-only).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function stripDisallowedBookingEventsFromQuestionnaireState(Tenant $tenant, array $state): array
    {
        $schedulingOn = (bool) $tenant->scheduling_module_enabled;
        if ($schedulingOn && ! $this->branchResolution->shouldFilterBookingNotificationEvents()) {
            return $state;
        }
        $ev = $state['events_enabled'] ?? [];
        if (! is_array($ev)) {
            return $state;
        }
        $state['events_enabled'] = array_values(array_filter(
            array_map('strval', $ev),
            static fn (string $k): bool => ! str_starts_with($k, 'booking.'),
        ));

        return $state;
    }

    private function productCalSubfieldsVisible(Get $get): bool
    {
        return (string) ($get('product_cal_uses_external') ?? '') !== '0';
    }

    /**
     * «Не указано» — показываем поле, чтобы был виден сценарий; «Да» — только если отмечен провайдер «Другое».
     */
    private function productCalOtherClarifyVisible(Get $get): bool
    {
        if (! $this->productCalSubfieldsVisible($get)) {
            return false;
        }
        $uses = (string) ($get('product_cal_uses_external') ?? '');
        if ($uses === '') {
            return true;
        }
        $providers = (array) ($get('product_cal_providers') ?? []);

        return in_array('other', array_map('strval', $providers), true);
    }

    private function recipientsSectionDescription(): string
    {
        return 'Здесь не больше одного адреса на канал: один email и один Telegram-чат. Это два разных способа доставки; при необходимости заполните оба — правила ниже будут отправлять уведомления на все указанные каналы. '
            .'Несколько почтовых ящиков или чатов в этой анкете не задаются: остальных получателей добавьте вручную в разделе «Получатели уведомлений» после применения. '
            .'Сообщения в Telegram идут через сервисного бота платформы (токен в консоли: «Провайдеры уведомлений»), не через контакт «Связь» на сайте. '
            .'После «Применить к кабинету» появятся соответствующие записи-получатели (нужны права).';
    }

    private function destTelegramHelperHtml(): HtmlString
    {
        return new HtmlString(
            '<div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">'
            .'<p><span class="font-medium text-gray-800 dark:text-gray-200">Какой бот:</span> нужен чат с '
            .'сервисным ботом платформы — тем же ботом, через который RentBase шлёт уведомления (токен задаётся в консоли платформы: «Провайдеры уведомлений», блок Telegram). '
            .'Имя @… бота в Telegram подскажет команда платформы; это не бот вашего бренда и не переписка с клиентами.</p>'
            .'<p><span class="font-medium text-gray-800 dark:text-gray-200">Какой диалог:</span> '
            .'ЛС — с аккаунта, куда нужны уведомления, откройте личный чат с этим ботом платформы и отправьте /start или сообщение, чтобы диалог существовал. '
            .'Группа — в группу добавлен тот же бот платформы, ему разрешено писать. '
            .'Канал — тот же бот добавлен администратором. Во всех случаях пишет именно бот платформы, а не произвольный контакт.</p>'
            .'<p><span class="font-medium text-gray-800 dark:text-gray-200">Формат поля:</span> числовой chat_id '
            .'(<code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-white/10">123456789</code> для ЛС, '
            .'<code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-white/10">-100…</code> для каналов и супергрупп) '
            .'или публичный <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-white/10">@username</code> канала.</p>'
            .'<p><span class="font-medium text-gray-800 dark:text-gray-200">Где взять id:</span> после того как чат с ботом платформы настроен — в карточке получателя в кабинете («Получатели уведомлений»). '
            .'Сторонние боты вроде @userinfobot / @getidsbot — это отдельные сервисы Telegram для просмотра id пользователя или чата; к боту платформы не относятся, используйте только чтобы скопировать цифры.</p>'
            .'</div>'
        );
    }

}
