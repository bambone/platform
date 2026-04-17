<?php

namespace App\Filament\Tenant\Pages;

use App\Models\TenantDomain;
use App\Models\TenantPushEventPreference;
use App\TenantPush\OneSignalExternalUserId;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushNotificationBindingSync;
use App\TenantPush\TenantPushDiagnosticCode;
use App\TenantPush\TenantPushDiagnosticsService;
use App\TenantPush\TenantPushOnesignalClient;
use App\TenantPush\TenantPushProviderStatus;
use App\TenantPush\TenantPushRecipientScope;
use App\TenantPush\TenantPushCrmRequestRecipientResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

        $tenant = currentTenant();
        if ($tenant === null) {
            abort(404);
        }

        $settings = $gate->ensureSettings($tenant);
        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();

        $domainOptions = $tenant->domains()
            ->where('status', TenantDomain::STATUS_ACTIVE)
            ->get()
            ->mapWithKeys(fn (TenantDomain $d) => [strtolower((string) $d->host) => (string) $d->host])
            ->all();

        $this->getSchema('form')->fill([
            'commercial_service_active' => $settings->commercial_service_active,
            'canonical_host' => $settings->canonical_host ?? (array_key_first($domainOptions) !== false ? array_key_first($domainOptions) : null),
            'onesignal_app_id' => $settings->onesignal_app_id,
            'onesignal_app_api_key' => '',
            'has_api_key' => array_key_exists('onesignal_app_api_key_encrypted', $settings->getAttributes())
                && $settings->getAttributes()['onesignal_app_api_key_encrypted'] !== null,
            'is_push_enabled' => $settings->is_push_enabled,
            'is_pwa_enabled' => $settings->is_pwa_enabled,
            'crm_push_enabled' => $pref?->is_enabled ?? false,
            'recipient_scope' => $pref?->recipient_scope ?? TenantPushRecipientScope::OwnerOnly->value,
            'selected_user_ids' => $pref?->selectedUserIds() ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $tenant = currentTenant();
        $gate = $tenant ? app(TenantPushFeatureGate::class)->evaluate($tenant) : null;
        $editable = $gate?->canEditSettings ?? false;

        $userOptions = [];
        if ($tenant !== null) {
            $tenant->users()->wherePivot('status', 'active')->get()->each(function ($u) use (&$userOptions): void {
                $userOptions[$u->id] = $u->email ?? ('#'.$u->id);
            });
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Доступность')
                    ->description('Платная услуга и тариф. Без активации услуги и соответствующего тарифа push недоступен (кроме принудительного включения платформой).')
                    ->schema([
                        Toggle::make('commercial_service_active')
                            ->label('Услуга push активирована (коммерция)')
                            ->disabled(! $editable),
                    ]),
                Section::make('Канонический домен для push')
                    ->schema([
                        Select::make('canonical_host')
                            ->label('Основной домен (HTTPS)')
                            ->options(fn () => $tenant ? $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->pluck('host', 'host')->all() : [])
                            ->disabled(! $editable)
                            ->nullable()
                            ->required(fn () => $tenant !== null && $tenant->domains()->where('status', TenantDomain::STATUS_ACTIVE)->exists()),
                    ]),
                Section::make('OneSignal')
                    ->schema([
                        TextInput::make('onesignal_app_id')
                            ->label('App ID')
                            ->disabled(! $editable),
                        TextInput::make('onesignal_app_api_key')
                            ->label('App API Key')
                            ->password()
                            ->revealable()
                            ->helperText(fn ($get) => $get('has_api_key') ? 'Ключ сохранён. Введите новый, чтобы заменить.' : null)
                            ->disabled(! $editable),
                        Toggle::make('is_push_enabled')->label('Включить отправку push')->disabled(! $editable),
                    ]),
                Section::make('PWA')
                    ->schema([
                        Toggle::make('is_pwa_enabled')->label('Включить динамический manifest')->disabled(! $editable),
                    ]),
                Section::make('События: новая заявка')
                    ->schema([
                        Toggle::make('crm_push_enabled')->label('Push при новой заявке с сайта')->disabled(! $editable),
                        Select::make('recipient_scope')
                            ->label('Кому')
                            ->options([
                                TenantPushRecipientScope::OwnerOnly->value => 'Владелец клиента',
                                TenantPushRecipientScope::SelectedUsers->value => 'Выбранные пользователи',
                                TenantPushRecipientScope::AllAdmins->value => 'Все администраторы кабинета',
                            ])
                            ->native(true)
                            ->disabled(! $editable),
                        Select::make('selected_user_ids')
                            ->label('Пользователи')
                            ->multiple()
                            ->options($userOptions)
                            ->native(true)
                            ->visible(fn ($get) => ($get('recipient_scope') ?? '') === TenantPushRecipientScope::SelectedUsers->value)
                            ->disabled(! $editable),
                    ]),
            ]);
    }

    public function save(
        TenantPushFeatureGate $gate,
        TenantPushNotificationBindingSync $bindingSync,
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

        $host = strtolower(trim((string) ($data['canonical_host'] ?? '')));
        $origin = $host !== '' ? 'https://'.$host : null;

        $settings->fill([
            'commercial_service_active' => (bool) ($data['commercial_service_active'] ?? false),
            'canonical_host' => $host !== '' ? $host : null,
            'canonical_origin' => $origin,
            'onesignal_app_id' => trim((string) ($data['onesignal_app_id'] ?? '')) ?: null,
            'is_push_enabled' => (bool) ($data['is_push_enabled'] ?? false),
            'is_pwa_enabled' => (bool) ($data['is_pwa_enabled'] ?? false),
        ]);

        $key = trim((string) ($data['onesignal_app_api_key'] ?? ''));
        if ($key !== '') {
            $settings->onesignal_app_api_key_encrypted = $key;
            $settings->onesignal_key_pending_verification = true;
            $settings->provider_status = TenantPushProviderStatus::Invalid->value;
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

        $pref->is_enabled = (bool) ($data['crm_push_enabled'] ?? false);
        $pref->recipient_scope = (string) ($data['recipient_scope'] ?? TenantPushRecipientScope::OwnerOnly->value);
        $pref->selected_user_ids_json = array_values(array_map('intval', $data['selected_user_ids'] ?? []));
        $pref->save();

        $bindingSync->syncCrmRequestCreated($tenant);

        Notification::make()->title('Сохранено')->success()->send();
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
        return [
            Action::make('verify')
                ->label('Проверить OneSignal')
                ->action('verifyOnesignal'),
            Action::make('test')
                ->label('Тестовый push')
                ->action('sendTestPush'),
        ];
    }

}
