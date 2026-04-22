<?php

namespace App\Filament\Tenant\Pages;

use App\Auth\AccessRoles;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPushEventPreference;
use App\Models\TenantPushSettings;
use App\TenantPush\OneSignalExternalUserId;
use App\TenantPush\TenantPushCrmRequestRecipientResolver;
use App\TenantPush\TenantPushDiagnosticCode;
use App\TenantPush\TenantPushDiagnosticsService;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushGuidedSetupState;
use App\TenantPush\TenantPushNotificationBindingSync;
use App\TenantPush\TenantPushOnesignalClient;
use App\TenantPush\TenantPushProviderStatus;
use App\TenantPush\TenantPushRecipientScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use UnitEnum;

class TenantPushPwaSettingsPage extends Page
{
    protected static ?string $navigationLabel = 'PWA и Push';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $title = 'PWA и Push (OneSignal)';

    protected static ?string $slug = 'settings/push-pwa';

    protected static ?int $navigationSort = 36;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.tenant.pages.tenant-push-pwa-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?string $lastOnesignalApiKeyOutcome = null;

    public static function canAccess(): bool
    {
        if (! Gate::allows('manage_settings')) {
            return false;
        }
        $tenant = currentTenant();
        if ($tenant === null) {
            return false;
        }

        return app(TenantPushFeatureGate::class)->evaluate($tenant)->canViewSection;
    }

    public function mount(TenantPushFeatureGate $gate): void
    {
        abort_unless(self::canAccess(), 403);

        $this->lastOnesignalApiKeyOutcome = null;

        $tenant = currentTenant();
        if ($tenant === null) {
            abort(404);
        }

        $this->fillFormFromSettings($tenant, $gate);
    }

    public function form(Schema $schema): Schema
    {
        $tenant = currentTenant();
        $gate = $tenant ? app(TenantPushFeatureGate::class)->evaluate($tenant) : null;
        $editable = $gate?->canEditSettings ?? false;

        $userOptions = [];
        if ($tenant !== null) {
            $tenant->users()
                ->wherePivot('status', 'active')
                ->wherePivotIn('role', AccessRoles::tenantMembershipRolesForPanel())
                ->orderBy('users.id')
                ->get()
                ->each(function ($u) use (&$userOptions): void {
                    $userOptions[$u->id] = $u->email ?? ('#'.$u->id);
                });
        }

        $supportUrl = trim((string) config('tenant_push.support_url', ''));
        $supportEmail = trim((string) config('tenant_push.support_email', ''));
        $supportFallback = 'Обратитесь в поддержку платформы для подключения push-услуги.';

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Доступность услуги')
                    ->description('Коммерческую активацию услуги настраивает платформа. Здесь — статус и что делать, если модули недоступны по тарифу.')
                    ->schema([
                        Placeholder::make('_commercial_status_readonly')
                            ->label('Push (коммерция и тариф)')
                            ->content(function () use ($tenant, $supportUrl, $supportEmail, $supportFallback): HtmlString {
                                if ($tenant === null) {
                                    return new HtmlString('—');
                                }
                                $tenant->loadMissing('pushSettings');
                                $active = (bool) ($tenant->pushSettings?->commercial_service_active ?? false);
                                if ($active) {
                                    return new HtmlString('Подключена. Отключение или смена условия — через поддержку платформы.');
                                }
                                $chunks = [
                                    'Не подключена: для вас пока нельзя опираться на рассчёт по заявкам, пока платформа не отметит коммерческую готовность.',
                                ];
                                if ($supportUrl !== '') {
                                    $href = $this->supportUrlAsHref($supportUrl);
                                    $chunks[] = '<a class="text-primary-600 underline dark:text-primary-400" href="'.e($href).'" rel="noopener noreferrer" target="_blank">Страница поддержки (внешняя ссылка)</a>';
                                }
                                if ($supportEmail !== '') {
                                    $chunks[] = 'Email: <a class="text-primary-600 underline dark:text-primary-400" href="mailto:'.e($supportEmail).'">'.e($supportEmail).'</a>';
                                }
                                if ($supportUrl === '' && $supportEmail === '') {
                                    $chunks[] = e($supportFallback);
                                }

                                return new HtmlString('<p>'.implode(' ', $chunks).'</p>');
                            }),
                    ]),
                Section::make('Шаг 1. Домен для push (HTTPS)')
                    ->description('Сайт и подписки в браузере привязываются к одному хосту с действующим SSL.')
                    ->schema([
                        Select::make('canonical_host')
                            ->label('Основной домен (HTTPS)')
                            ->options(fn () => $tenant ? $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->pluck('host', 'host')->all() : [])
                            ->disabled(fn () => ! $editable || ($tenant !== null && $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->doesntExist()))
                            ->nullable()
                            ->required(fn () => $tenant !== null && $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->exists())
                            ->helperText(function ($get) use ($tenant): ?string {
                                if ($tenant === null) {
                                    return null;
                                }
                                if ($tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->doesntExist()) {
                                    return 'Сначала подключите и активируйте домен в разделе доменов или через платформу.';
                                }
                                $h = strtolower(trim((string) ($get('canonical_host') ?? '')));
                                if ($h === '') {
                                    return null;
                                }
                                $domain = $tenant->domains()
                                    ->where('status', TenantDomain::STATUS_ACTIVE)
                                    ->get()
                                    ->first(fn (TenantDomain $d): bool => strtolower((string) $d->host) === $h);
                                if ($domain === null) {
                                    return null;
                                }
                                if (in_array((string) $domain->ssl_status, [TenantDomain::SSL_ISSUED, TenantDomain::SSL_NOT_REQUIRED], true)) {
                                    return null;
                                }

                                return 'Для выбранного хоста ещё нет пригодного SSL для публичного HTTPS — проверьте статус в настройках домена.';
                            }),
                    ]),
                Section::make('Шаг 2. OneSignal: ключи приложения')
                    ->description('В кабинете OneSignal: Settings → Keys & IDs: скопируйте App ID и REST API key (тот, что с правами на доставку).')
                    ->schema([
                        Hidden::make('has_api_key'),
                        Placeholder::make('onesignal_hint')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<ol class="list-decimal space-y-1 pl-4 text-sm text-gray-600 dark:text-gray-400">'
                                .'<li>Откройте ваше приложение в <a class="text-primary-600 underline" href="https://dashboard.onesignal.com/" rel="noopener noreferrer" target="_blank">OneSignal</a>.</li>'
                                .'<li>Скопируйте <strong>App ID</strong> (UUID) в поле ниже.</li>'
                                .'<li>Скопируйте <strong>REST API Key</strong> в «App API Key» (секрет не отображаем после сохранения).</li>'
                                .'<li>Нажмите «Сохранить», затем «Проверить OneSignal» внизу страницы или в шапке.</li>'
                                .'</ol>'
                            )),
                        TextInput::make('onesignal_app_id')
                            ->label('App ID')
                            ->disabled(! $editable)
                            ->maxLength(120),
                        Placeholder::make('onesignal_app_id_status')
                            ->hiddenLabel()
                            ->content(function ($get): HtmlString {
                                if (trim((string) $get('onesignal_app_id')) !== '') {
                                    return new HtmlString('App ID: <span class="font-medium">задан</span> — в поле выше UUID из кабинета OneSignal; после смены нажмите «Сохранить».');
                                }

                                return new HtmlString('App ID: <span class="font-medium">не задан</span> — вставьте UUID из кабинета OneSignal.');
                            }),
                        Placeholder::make('onesignal_key_persistent')
                            ->hiddenLabel()
                            ->content(function ($get) use ($tenant): HtmlString {
                                if ($tenant === null) {
                                    return new HtmlString('—');
                                }
                                if ($get('has_api_key')) {
                                    return new HtmlString('App API Key: сохранён в системе. Чтобы сменить — введите новый в поле ниже или сбросьте тогглом «Сбросить сохранённый App API Key».');
                                }

                                return new HtmlString('App API Key: <span class="font-medium">в системе не сохранён</span> — введите и нажмите «Сохранить».');
                            }),
                        Placeholder::make('onesignal_key_last_outcome')
                            ->hiddenLabel()
                            ->visible(fn (): bool => $this->lastOnesignalApiKeyOutcome !== null)
                            ->content(fn (): string => (string) $this->lastOnesignalApiKeyOutcome),
                        TextInput::make('onesignal_app_api_key')
                            ->label('App API Key')
                            ->password()
                            ->revealable()
                            ->placeholder(fn ($get) => $get('has_api_key') ? '•••• … сохранён' : 'Ключ не задан')
                            ->helperText('Если ключ уже в базе, поле пустое — введите новый только чтобы заменить. Для проверки и теста важен ключ, <strong>сохранённый</strong> в системе.')
                            ->disabled(! $editable),
                        Toggle::make('clear_onesignal_api_key')
                            ->label('Сбросить сохранённый App API Key')
                            ->helperText('Удаляет секрет из настроек. Пока тоггл включён, введённое в поле выше не применяется. Чтобы сменить ключ — выключите сброс, вставьте новый и сохраните.')
                            ->default(false)
                            ->visible(fn ($get) => (bool) $get('has_api_key'))
                            ->disabled(! $editable),
                        Toggle::make('is_push_enabled')
                            ->label('Включить отправку push через OneSignal')
                            ->helperText(function ($get) {
                                if ($get('is_push_enabled')) {
                                    return 'Выключается одним переключателем, если больше не нужно доставлять с этого приложения.';
                                }
                                $g = $this->guidedStateFromGet($get);
                                if (! $g->canEnablePush) {
                                    return $g->primaryReasonMessage !== '' ? $g->primaryReasonMessage : $g->primaryReason->userMessage();
                                }

                                return 'Доступно, когда выбран HTTPS-домен с SSL, указаны и сохранены App ID и App API Key.';
                            })
                            ->disabled(fn ($get) => ! $editable
                                || (! $get('is_push_enabled') && ! $this->guidedStateFromGet($get)->canEnablePush)),
                    ]),
                Section::make('Шаг 3. События: новая заявка')
                    ->description('Получатели — те, кто увидит push в кабинете, если у них есть подписка OneSignal.')
                    ->schema([
                        Toggle::make('crm_push_enabled')
                            ->label('Push о новой заявке с сайта')
                            ->helperText(function ($get) {
                                $g = $this->guidedStateFromGet($get);
                                if ((bool) $get('crm_push_enabled') && $g->canEnableCrmPush) {
                                    return 'Доставка зависит от подписок OneSignal на устройствах выбранных сотрудников.';
                                }
                                if ((bool) $get('crm_push_enabled') && ! $g->canEnableCrmPush) {
                                    return $g->primaryReasonMessage !== '' ? $g->primaryReasonMessage : $g->primaryReason->userMessage();
                                }
                                if (! $g->canEnableCrmPush) {
                                    return 'Сначала выберите домен с SSL, укажите и сохраните OneSignal, выполните проверку, включите общую отправку push и настройте получателей — затем снова откройте этот тумблер.';
                                }

                                return 'Можно включать уведомления о заявке: базовая цепочка OneSignal в порядке.';
                            })
                            ->disabled(fn ($get) => ! $editable
                                || (! $get('crm_push_enabled') && ! $this->guidedStateFromGet($get)->canEnableCrmPush)),
                        Select::make('recipient_scope')
                            ->label('Получатели уведомлений (кому отправлять push)')
                            ->options([
                                TenantPushRecipientScope::OwnerOnly->value => 'Владелец клиента',
                                TenantPushRecipientScope::SelectedUsers->value => 'Выбранные сотрудники',
                                TenantPushRecipientScope::AllAdmins->value => 'Все, у кого есть доступ к этому кабинету',
                            ])
                            ->native(true)
                            ->disabled(! $editable),
                        Select::make('selected_user_ids')
                            ->label('Выберите сотрудников')
                            ->multiple()
                            ->options($userOptions)
                            ->native(true)
                            ->visible(fn ($get) => ($get('recipient_scope') ?? '') === TenantPushRecipientScope::SelectedUsers->value)
                            ->disabled(! $editable),
                    ]),
                Section::make('Дополнительно: PWA')
                    ->description('Второстепенные поля, не связанные с базовой цепочкой OneSignal. Базовый manifest сайта отдаётся в любом случае.')
                    ->collapsed()
                    ->schema([
                        Toggle::make('is_pwa_enabled')
                            ->label('Расширенный сценарий PWA')
                            ->helperText('Включает сопутствующие проверки и кастомные поля. Не влияет на наличие manifest в браузере.')
                            ->disabled(! $editable),
                    ]),
            ]);
    }

    /**
     * @param  callable(string): mixed  $get
     */
    protected function guidedStateFromGet(callable $get): TenantPushGuidedSetupState
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            throw new \RuntimeException('Current tenant is required for guided push state.');
        }
        $featureGate = app(TenantPushFeatureGate::class);
        $g = $featureGate->evaluate($tenant);
        $settings = $featureGate->resolveSettingsForDisplay($tenant);
        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();
        $form = [
            'canonical_host' => $get('canonical_host'),
            'onesignal_app_id' => $get('onesignal_app_id'),
            'onesignal_app_api_key' => $get('onesignal_app_api_key'),
            'clear_onesignal_api_key' => (bool) $get('clear_onesignal_api_key'),
            'is_push_enabled' => (bool) $get('is_push_enabled'),
            'crm_push_enabled' => (bool) $get('crm_push_enabled'),
            'recipient_scope' => (string) ($get('recipient_scope') ?? ''),
            'selected_user_ids' => is_array($get('selected_user_ids')) ? $get('selected_user_ids') : [],
        ];

        return TenantPushGuidedSetupState::make($tenant, $g, $settings, $pref, $form);
    }

    public function guidedStateForActions(): TenantPushGuidedSetupState
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            throw new \RuntimeException('Current tenant is required for guided push state.');
        }
        $featureGate = app(TenantPushFeatureGate::class);
        $g = $featureGate->evaluate($tenant);
        $settings = $featureGate->resolveSettingsForDisplay($tenant);
        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();
        $form = $this->getSchema('form')->getState();

        return TenantPushGuidedSetupState::make($tenant, $g, $settings, $pref, $form);
    }

    public function save(
        TenantPushFeatureGate $gate,
        TenantPushNotificationBindingSync $bindingSync,
        TenantPushCrmRequestRecipientResolver $recipientResolver,
    ): void {
        abort_unless(Gate::allows('manage_settings'), 403);

        $tenant = currentTenant();
        if ($tenant === null) {
            return;
        }

        $g = $gate->evaluate($tenant);
        if (! $g->canEditSettings) {
            Notification::make()->title('Нет прав на изменение.')->danger()->send();

            return;
        }

        $data = $this->getSchema('form')->getState();
        $settings = $gate->ensureSettings($tenant);
        $prefForGuided = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();
        $guided = TenantPushGuidedSetupState::make(
            $tenant,
            $g,
            $settings,
            $prefForGuided,
            $data,
        );
        if ((bool) ($data['is_push_enabled'] ?? false) && ! $guided->canEnablePush) {
            Notification::make()
                ->title('Пока нельзя включить отправку push')
                ->body($guided->primaryReasonMessage !== '' ? $guided->primaryReasonMessage : $guided->primaryReason->userMessage())
                ->danger()
                ->send();

            return;
        }
        if ((bool) ($data['crm_push_enabled'] ?? false) && ! $guided->canEnableCrmPush) {
            Notification::make()
                ->title('Пока нельзя включить уведомления о заявке')
                ->body($guided->primaryReasonMessage !== '' ? $guided->primaryReasonMessage : $guided->primaryReason->userMessage())
                ->danger()
                ->send();

            return;
        }

        $this->lastOnesignalApiKeyOutcome = null;

        $activeHostsLower = $tenant->domains()
            ->where('status', TenantDomain::STATUS_ACTIVE)
            ->pluck('host')
            ->map(fn ($h): string => strtolower(trim((string) $h)))
            ->filter(fn (string $h): bool => $h !== '')
            ->values();
        $hasActiveDomains = $activeHostsLower->isNotEmpty();

        $submittedHost = strtolower(trim((string) ($data['canonical_host'] ?? '')));

        if ($hasActiveDomains) {
            if ($submittedHost === '') {
                Notification::make()->title('Укажите основной домен из списка активных.')->danger()->send();

                return;
            }
            if (! $activeHostsLower->contains($submittedHost)) {
                Notification::make()->title('Допустимы только активные домены этого клиента.')->danger()->send();

                return;
            }
        } else {
            $submittedHost = '';
        }

        $host = $submittedHost;
        $origin = $host !== '' ? 'https://'.$host : null;

        $previousAppId = strtolower(trim((string) ($settings->onesignal_app_id ?? '')));
        $newAppIdTrimmed = trim((string) ($data['onesignal_app_id'] ?? ''));
        $newAppId = $newAppIdTrimmed !== '' ? $newAppIdTrimmed : null;
        $newAppIdNorm = $newAppId !== null ? strtolower($newAppId) : '';

        $clear = (bool) ($data['clear_onesignal_api_key'] ?? false);
        $key = trim((string) ($data['onesignal_app_api_key'] ?? ''));
        $hadKey = $this->modelHasStoredOnesignalKey($settings);

        $settings->fill([
            'canonical_host' => $host !== '' ? $host : null,
            'canonical_origin' => $origin,
            'onesignal_app_id' => $newAppId,
            'is_push_enabled' => (bool) ($data['is_push_enabled'] ?? false),
            'is_pwa_enabled' => (bool) ($data['is_pwa_enabled'] ?? false),
        ]);

        if ($clear) {
            if ($hadKey) {
                $this->lastOnesignalApiKeyOutcome = 'Ключ удалён';
            }
            $settings->onesignal_app_api_key_encrypted = null;
            $settings->onesignal_key_pending_verification = true;
            $settings->provider_status = TenantPushProviderStatus::Invalid->value;
            $settings->onesignal_config_verified_at = null;
            $settings->onesignal_last_verification_error = null;
        } elseif ($key !== '') {
            $this->lastOnesignalApiKeyOutcome = $hadKey ? 'Ключ обновлён' : 'Ключ сохранён';
            $settings->onesignal_app_api_key_encrypted = $key;
            $settings->onesignal_key_pending_verification = true;
            $settings->provider_status = TenantPushProviderStatus::Invalid->value;
            $settings->onesignal_config_verified_at = null;
            $settings->onesignal_last_verification_error = null;
        } elseif ($previousAppId !== $newAppIdNorm) {
            $settings->provider_status = TenantPushProviderStatus::Invalid->value;
            $settings->onesignal_key_pending_verification = true;
            $settings->onesignal_config_verified_at = null;
            $settings->onesignal_last_verification_error = null;
        }

        $settings->save();

        $pref = TenantPushEventPreference::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'event_key' => 'crm_request.created'],
            [
                'is_enabled' => false,
                'delivery_mode' => 'immediate',
                'recipient_scope' => TenantPushRecipientScope::OwnerOnly->value,
            ],
        );

        $scope = TenantPushRecipientScope::tryFrom((string) ($data['recipient_scope'] ?? ''))
            ?? TenantPushRecipientScope::OwnerOnly;

        $pref->is_enabled = (bool) ($data['crm_push_enabled'] ?? false);
        $pref->recipient_scope = $scope->value;

        if ($scope !== TenantPushRecipientScope::SelectedUsers) {
            $pref->selected_user_ids_json = [];
        } else {
            $raw = $data['selected_user_ids'] ?? [];
            $ids = is_array($raw) ? $raw : [];
            $pref->selected_user_ids_json = $recipientResolver->sanitizeSelectedUserIdsForSave($tenant, $ids);
        }

        $pref->save();

        $bindingSync->syncCrmRequestCreated($tenant);

        Notification::make()->title('Сохранено')->success()->send();

        if ($settings->exists) {
            $settings->refresh();
        }
        if ($pref->exists) {
            $pref->refresh();
        }
        $this->fillFormFromSettings($tenant, $gate);
    }

    private function fillFormFromSettings(Tenant $tenant, TenantPushFeatureGate $gate): void
    {
        $settings = $gate->resolveSettingsForDisplay($tenant);
        if ($settings->exists) {
            $settings->refresh();
        }
        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();

        $this->getSchema('form')->fill($this->formStateArrayForTenant($tenant, $settings, $pref));
    }

    /**
     * Полное состояние формы из БД (как на платформе: после save — визу = данные, включая нормализованные id и хост).
     *
     * @return array<string, mixed>
     */
    private function formStateArrayForTenant(
        Tenant $tenant,
        TenantPushSettings $settings,
        ?TenantPushEventPreference $pref,
    ): array {
        $activeDomains = $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->get();
        $domainOptions = $activeDomains
            ->mapWithKeys(fn (TenantDomain $d) => [strtolower((string) $d->host) => (string) $d->host])
            ->all();

        $canonicalHost = $settings->canonical_host;
        if (($canonicalHost === null || $canonicalHost === '') && $activeDomains->count() === 1) {
            $canonicalHost = strtolower((string) $activeDomains->first()->host);
        } elseif (($canonicalHost === null || $canonicalHost === '') && $domainOptions !== []) {
            $first = array_key_first($domainOptions);
            $canonicalHost = $first !== null ? (string) $first : null;
        }
        if (is_string($canonicalHost) && $canonicalHost !== '') {
            $canonicalHost = strtolower(trim($canonicalHost));
        }

        $attrs = $settings->getAttributes();
        $hasKey = array_key_exists('onesignal_app_api_key_encrypted', $attrs)
            && $attrs['onesignal_app_api_key_encrypted'] !== null
            && $attrs['onesignal_app_api_key_encrypted'] !== '';

        return [
            'canonical_host' => $canonicalHost,
            'onesignal_app_id' => $settings->onesignal_app_id,
            'onesignal_app_api_key' => '',
            'clear_onesignal_api_key' => false,
            'has_api_key' => $hasKey,
            'is_push_enabled' => (bool) $settings->is_push_enabled,
            'is_pwa_enabled' => (bool) $settings->is_pwa_enabled,
            'crm_push_enabled' => (bool) ($pref?->is_enabled ?? false),
            'recipient_scope' => $pref?->recipient_scope ?? TenantPushRecipientScope::OwnerOnly->value,
            'selected_user_ids' => $pref?->selectedUserIds() ?? [],
        ];
    }

    private function supportUrlAsHref(string $raw): string
    {
        $t = trim($raw);
        if ($t === '' || str_starts_with($t, 'http://') || str_starts_with($t, 'https://') || str_starts_with($t, '//')) {
            return $t;
        }
        if (str_starts_with($t, 'mailto:')) {
            return $t;
        }

        return 'https://'.$t;
    }

    private function modelHasStoredOnesignalKey(TenantPushSettings $settings): bool
    {
        $attrs = $settings->getAttributes();

        return array_key_exists('onesignal_app_api_key_encrypted', $attrs)
            && $attrs['onesignal_app_api_key_encrypted'] !== null
            && $attrs['onesignal_app_api_key_encrypted'] !== '';
    }

    public function verifyOnesignal(TenantPushFeatureGate $gate, TenantPushOnesignalClient $client, TenantPushDiagnosticsService $diagnostics): void
    {
        $tenant = currentTenant();
        if ($tenant === null || ! $gate->evaluate($tenant)->canEditSettings) {
            return;
        }

        $settings = $gate->ensureSettings($tenant);
        $appId = trim((string) $settings->onesignal_app_id);
        $decrypted = $settings->onesignal_app_api_key_encrypted;

        if ($appId === '' || $decrypted === null || $decrypted === '') {
            Notification::make()->title('Укажите App ID и App API Key.')->danger()->send();

            return;
        }

        $result = $client->verifyAppCredentials($appId, $decrypted);
        /** @var TenantPushDiagnosticCode $code */
        $code = $result['code'] ?? TenantPushDiagnosticCode::Unknown;

        if ($result['ok']) {
            $settings->provider_status = TenantPushProviderStatus::Verified->value;
            $settings->onesignal_config_verified_at = now();
            $settings->onesignal_last_verification_error = null;
            $settings->onesignal_key_pending_verification = false;
            $settings->save();
            $diagnostics->record($tenant, 'onesignal_verify', 'ok', $code);

            Notification::make()->title('Подключение к OneSignal подтверждено.')->success()->send();

            return;
        }

        $msg = is_array($result['body']) ? json_encode($result['body'], JSON_UNESCAPED_UNICODE) : (string) $result['body'];
        $settings->provider_status = TenantPushProviderStatus::Invalid->value;
        $settings->onesignal_last_verification_error = $msg;
        $settings->save();

        $diagnostics->record($tenant, 'onesignal_verify', 'error', $code, mb_substr($msg, 0, 2000));

        $verifyFailureBody = 'Код: '.$code->value.'. '.mb_substr($msg, 0, 260);

        Notification::make()
            ->title('Проверка не прошла')
            ->body($verifyFailureBody)
            ->danger()
            ->send();
    }

    public function sendTestPush(TenantPushFeatureGate $gate, TenantPushOnesignalClient $client, TenantPushDiagnosticsService $diagnostics): void
    {
        $tenant = currentTenant();
        $user = Auth::user();
        if ($tenant === null || $user === null || ! $gate->evaluate($tenant)->canEditSettings) {
            return;
        }

        $settings = $gate->ensureSettings($tenant);
        $appId = trim((string) $settings->onesignal_app_id);
        try {
            $key = $settings->onesignal_app_api_key_encrypted;
        } catch (\Throwable) {
            $key = null;
        }

        if ($appId === '' || $key === null || $key === '') {
            Notification::make()->title('Сначала сохраните ключи OneSignal.')->danger()->send();

            return;
        }

        $externalId = OneSignalExternalUserId::format((int) $tenant->id, (int) $user->id);

        $result = $client->sendTestToExternalUserIds(
            $appId,
            $key,
            [$externalId],
            'RentBase: тест',
            'Тестовое уведомление',
        );

        /** @var TenantPushDiagnosticCode $code */
        $code = $result['code'] ?? TenantPushDiagnosticCode::Unknown;

        $settings->test_push_last_sent_at = now();
        $settings->test_push_last_result_status = $result['ok'] ? 'ok' : 'error';
        $settings->test_push_last_result_message = is_array($result['body'])
            ? json_encode($result['body'], JSON_UNESCAPED_UNICODE)
            : (string) $result['body'];
        $settings->save();

        $msg = $settings->test_push_last_result_message;
        $diagnostics->record(
            $tenant,
            'onesignal_test_push',
            $result['ok'] ? 'ok' : 'error',
            $code,
            is_string($msg) ? mb_substr($msg, 0, 2000) : null,
        );

        if ($result['ok']) {
            $testOkBody = 'Это проверяет ключи и external id '.$externalId.', а не готовность всей цепочки для всех получателей.';

            Notification::make()
                ->title('Тестовое уведомление отправлено OneSignal.')
                ->body($testOkBody)
                ->success()
                ->send();
        } else {
            $testErrBody = 'Код: '.$code->value.'. '.mb_substr((string) $msg, 0, 360);

            Notification::make()
                ->title('Ошибка отправки теста')
                ->body($testErrBody)
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $tenant = currentTenant();
        $canEdit = $tenant !== null && app(TenantPushFeatureGate::class)->evaluate($tenant)->canEditSettings;

        $actions = [
            TenantPanelHintHeaderAction::makeLines(
                'tenantPushPwaWhatIs',
                [
                    'Web Push и PWA через OneSignal: ключи приложения, сегменты получателей, тестовая отправка.',
                    '',
                    'Проверка в шапке валидирует конфигурацию, но не всю цепочку доставки для всех пользователей.',
                ],
                'Справка по Push и PWA',
            ),
        ];

        if ($canEdit) {
            $actions[] = Action::make('verify')
                ->label('Проверить OneSignal')
                ->action('verifyOnesignal')
                ->disabled(fn () => ! $this->guidedStateForActions()->canVerifyOnesignal)
                ->tooltip(function (): ?string {
                    $m = $this->guidedStateForActions()->verifyActionDisabledMessage;

                    return $m === '' ? null : $m;
                });
            $actions[] = Action::make('test')
                ->label('Тестовый push')
                ->action('sendTestPush')
                ->disabled(fn () => ! $this->guidedStateForActions()->canSendTestPush)
                ->tooltip(function (): ?string {
                    $m = $this->guidedStateForActions()->testPushActionDisabledMessage;

                    return $m === '' ? null : $m;
                });
        }

        return $actions;
    }
}
