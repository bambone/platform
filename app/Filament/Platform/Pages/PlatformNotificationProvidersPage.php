<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use App\Services\Platform\PlatformNotificationSettings;
use App\Services\Platform\VapidKeyPairGenerator;
use App\Support\Recipients\RecipientListParser;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use UnitEnum;

class PlatformNotificationProvidersPage extends Page
{
    use GrantsPlatformPageAccess;

    protected static ?string $navigationLabel = 'Уведомления (провайдеры)';

    protected static ?string $title = 'Провайдеры уведомлений';

    protected static ?string $slug = 'notification-providers';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.platform.notification-providers';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?string $lastTelegramTokenOutcome = null;

    public ?string $lastVapidKeypairOutcome = null;

    public function mount(PlatformNotificationSettings $settings): void
    {
        $this->lastTelegramTokenOutcome = null;
        $this->lastVapidKeypairOutcome = null;
        $this->fillFormFromSettings($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Обычные каналы')
                    ->description('Быстрое отключение канала на всей платформе. Выключенный канал не используется для новых уведомлений.')
                    ->schema([
                        Toggle::make('channel_email_enabled')->label('Электронная почта'),
                        Toggle::make('channel_telegram_enabled')->label('Telegram'),
                    ])->columns(2),
                Section::make('Технические интеграции')
                    ->description('Обычно требуют настройки разработчиком и не нужны для базовой работы email/Telegram.')
                    ->schema([
                        Toggle::make('channel_webhook_enabled')->label('Входящий webhook (HTTP)'),
                        Toggle::make('channel_web_push_enabled')->label('Push в браузере (Web Push, VAPID)'),
                        Toggle::make('channel_web_push_onesignal_enabled')->label('OneSignal Web Push (тенанты)'),
                    ])->columns(2),
                Section::make('Telegram')
                    ->schema([
                        TextInput::make('telegram_bot_token')
                            ->label('Токен бота Telegram')
                            ->password()
                            ->revealable()
                            ->placeholder(fn (): string => $this->telegramTokenFieldPlaceholder())
                            ->helperText('Выдаётся в @BotFather. Если токен уже сохранён, оставьте поле пустым, чтобы не менять его. Чтобы удалить токен, включите «Сбросить сохранённый токен» ниже.'),
                        Placeholder::make('telegram_token_persistent_status')
                            ->hiddenLabel()
                            ->content(fn (): string => $this->telegramTokenPersistentStatus()),
                        Placeholder::make('telegram_token_last_outcome')
                            ->hiddenLabel()
                            ->visible(fn (): bool => $this->lastTelegramTokenOutcome !== null)
                            ->content(fn (): string => (string) $this->lastTelegramTokenOutcome),
                        Toggle::make('clear_telegram_bot_token')
                            ->label('Сбросить сохранённый токен')
                            ->helperText('Удаляет токен из настроек платформы. Новое значение в поле выше при этом игнорируется.')
                            ->default(false),
                        TextInput::make('platform_contact_chat_ids')
                            ->label('Chat ID для заявок с лендинга')
                            ->helperText(new HtmlString(
                                'Укажите <strong>числовой chat_id</strong> (пользователь, группа или канал), а не @username бота.<br>'
                                .'<strong>Правильно:</strong> <code>123456789</code>, <code>-1001234567890</code> — можно несколько через запятую.<br>'
                                .'<strong>Неправильно:</strong> <code>@gman1990_bot</code> — это username, сюда не подходит.'
                            ))
                            ->maxLength(4000),
                    ]),
                Section::make('Web Push (VAPID)')
                    ->description($this->vapidSectionDescription())
                    ->schema([
                        Placeholder::make('vapid_pair_persistent_status')
                            ->hiddenLabel()
                            ->content(fn (): string => $this->vapidPairPersistentStatus()),
                        Placeholder::make('vapid_pair_last_outcome')
                            ->hiddenLabel()
                            ->visible(fn (): bool => $this->lastVapidKeypairOutcome !== null)
                            ->content(fn (): string => (string) $this->lastVapidKeypairOutcome),
                        TextInput::make('vapid_public')
                            ->label('Публичный ключ')
                            ->helperText('Пара public/private задаётся вместе. Смена публичного ключа без приватного (при уже сохранённом приватном) блокируется — введите оба ключа или сбросьте пару тогглом ниже.'),
                        TextInput::make('vapid_private')
                            ->label('Приватный ключ')
                            ->password()
                            ->revealable()
                            ->placeholder(fn (): string => $this->vapidPrivateFieldPlaceholder())
                            ->helperText('Если приватный ключ уже сохранён, оставьте поле пустым, чтобы не менять его (пока не меняете публичный ключ).'),
                        Toggle::make('clear_vapid_keys')
                            ->label('Сбросить ключи VAPID')
                            ->helperText('Удаляет оба ключа из настроек. Поля выше при включённом сбросе не применяются.')
                            ->default(false),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateVapidKeypair')
                ->label('Сгенерировать VAPID-ключи')
                ->action(function (VapidKeyPairGenerator $generator, PlatformNotificationSettings $settings): void {
                    try {
                        $pair = $generator->generate();
                        $settings->setVapidKeypair($pair['public'], $pair['private']);
                        $this->lastVapidKeypairOutcome = 'Новая пара VAPID ключей сгенерирована и сохранена.';
                        $this->lastTelegramTokenOutcome = null;
                        $this->fillFormFromSettings(app(PlatformNotificationSettings::class));
                        Notification::make()
                            ->title('VAPID-ключи сохранены')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Не удалось сгенерировать VAPID-ключи')
                            ->body('Проверьте OpenSSL: задайте переменную окружения OPENSSL_CONF на путь к рабочему openssl.cnf (часто это файл рядом с PHP, например extras/ssl/openssl.cnf, либо системный /etc/ssl/openssl.cnf) и убедитесь, что EC-ключи prime256v1 доступны.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function save(PlatformNotificationSettings $settings): void
    {
        $data = $this->getSchema('form')->getState();

        $clearVapid = (bool) ($data['clear_vapid_keys'] ?? false);
        $pub = trim((string) ($data['vapid_public'] ?? ''));
        $priv = trim((string) ($data['vapid_private'] ?? ''));

        if (! $clearVapid) {
            if ($pub !== '' && $priv === '' && $settings->vapidPrivateKeyDecrypted() === null) {
                Notification::make()->title('Укажите приватный VAPID ключ или сбросьте ключи VAPID.')->danger()->send();

                return;
            }

            if ($pub !== '' && $priv === '' && $settings->vapidPrivateKeyDecrypted() !== null) {
                $storedPub = $settings->vapidPublicKey() ?? '';
                if ($storedPub !== '' && $pub !== $storedPub) {
                    Notification::make()->title('Новый публичный ключ сохраняется только вместе с приватным. Введите приватный ключ или сбросьте VAPID и задайте пару заново.')->danger()->send();

                    return;
                }
            }

            if ($pub === '' && $priv !== '') {
                Notification::make()->title('Укажите публичный VAPID ключ вместе с приватным.')->danger()->send();

                return;
            }

            if ($pub === '' && $priv === '' && $settings->vapidPublicKey() !== null) {
                Notification::make()->title('Чтобы удалить ключи VAPID, включите «Сбросить ключи VAPID». Публичный ключ нельзя очистить отдельно.')->danger()->send();

                return;
            }
        }

        $chatIdsRaw = trim((string) ($data['platform_contact_chat_ids'] ?? ''));
        if ($this->platformContactChatIdsContainAtPrefixedToken($chatIdsRaw)) {
            Notification::make()
                ->title('Некорректные chat_id')
                ->body('Укажите числовой chat_id, а не @username. Значения, начинающиеся с «@» (включая @gman1990_bot), в этом поле не сохраняются.')
                ->danger()
                ->send();

            return;
        }

        $hadTelegramToken = $this->hasStoredTelegramToken($settings);
        $hadVapidPair = $this->hasStoredVapidPair($settings);

        $this->lastTelegramTokenOutcome = null;
        $this->lastVapidKeypairOutcome = null;

        $settings->setChannelEnabled('email', (bool) ($data['channel_email_enabled'] ?? false));
        $settings->setChannelEnabled('telegram', (bool) ($data['channel_telegram_enabled'] ?? false));
        $settings->setChannelEnabled('webhook', (bool) ($data['channel_webhook_enabled'] ?? false));
        $settings->setChannelEnabled('web_push', (bool) ($data['channel_web_push_enabled'] ?? false));
        $settings->setChannelEnabled('web_push_onesignal', (bool) ($data['channel_web_push_onesignal_enabled'] ?? false));

        if ((bool) ($data['clear_telegram_bot_token'] ?? false)) {
            if ($hadTelegramToken) {
                $this->lastTelegramTokenOutcome = 'Токен удалён';
            }
            $settings->setTelegramBotToken(null);
        } else {
            $token = trim((string) ($data['telegram_bot_token'] ?? ''));
            if ($token !== '') {
                $this->lastTelegramTokenOutcome = $hadTelegramToken ? 'Токен обновлён' : 'Токен сохранён';
                $settings->setTelegramBotToken($token);
            }
        }

        $settings->setPlatformContactTelegramChatIds($chatIdsRaw !== '' ? $chatIdsRaw : null);

        if ($clearVapid) {
            if ($hadVapidPair) {
                $this->lastVapidKeypairOutcome = 'Пара VAPID ключей удалена';
            }
            $settings->clearVapidKeys();
        } elseif ($pub !== '' && $priv !== '') {
            $this->lastVapidKeypairOutcome = $hadVapidPair ? 'Пара VAPID ключей обновлена' : 'Пара VAPID ключей сохранена';
            $settings->setVapidKeypair($pub, $priv);
        }

        Notification::make()->title('Сохранено')->success()->send();

        $this->fillFormFromSettings(app(PlatformNotificationSettings::class));
    }

    private function fillFormFromSettings(PlatformNotificationSettings $settings): void
    {
        $this->getSchema('form')->fill([
            'channel_email_enabled' => $settings->isChannelEnabled('email'),
            'channel_telegram_enabled' => $settings->isChannelEnabled('telegram'),
            'channel_webhook_enabled' => $settings->isChannelEnabled('webhook'),
            'channel_web_push_enabled' => $settings->isChannelEnabled('web_push'),
            'channel_web_push_onesignal_enabled' => $settings->isChannelEnabled('web_push_onesignal'),
            'telegram_bot_token' => '',
            'clear_telegram_bot_token' => false,
            'platform_contact_chat_ids' => $settings->platformContactTelegramChatIdsRaw(),
            'vapid_public' => $settings->vapidPublicKey() ?? '',
            'vapid_private' => '',
            'clear_vapid_keys' => false,
        ]);
    }

    private function telegramTokenFieldPlaceholder(): string
    {
        return $this->hasStoredTelegramToken(app(PlatformNotificationSettings::class))
            ? '•••••••••••• сохранён'
            : 'Токен не задан';
    }

    private function telegramTokenPersistentStatus(): string
    {
        return $this->hasStoredTelegramToken(app(PlatformNotificationSettings::class))
            ? 'Токен сохранён'
            : 'Токен не задан';
    }

    private function vapidPrivateFieldPlaceholder(): string
    {
        $settings = app(PlatformNotificationSettings::class);

        return $settings->vapidPrivateKeyDecrypted() !== null
            ? '•••••••••••• сохранён'
            : 'Приватный ключ не задан';
    }

    private function vapidPairPersistentStatus(): string
    {
        $settings = app(PlatformNotificationSettings::class);
        $pub = $settings->vapidPublicKey();
        $priv = $settings->vapidPrivateKeyDecrypted();

        if ($pub !== null && $priv !== null) {
            return 'Пара VAPID ключей сохранена';
        }

        if ($pub === null && $priv === null) {
            return 'Ключи не заданы';
        }

        return 'Конфигурация VAPID неполная (ожидается пара public + private).';
    }

    private function vapidSectionDescription(): HtmlString
    {
        return new HtmlString(
            '<p>Web Push — браузерные push-уведомления для сайта. Этот блок <strong>не нужен</strong>, если вы используете только email или Telegram.</p>'
            .'<p><strong>Когда нужно:</strong> если подключаете доставку уведомлений в браузер пользователя (стандарт Web Push).</p>'
            .'<p><strong>Откуда ключи:</strong> это пара public/private в формате VAPID; их можно ввести вручную или <strong>сгенерировать кнопкой «Сгенерировать VAPID-ключи»</strong> в шапке страницы — пара сразу сохраняется в настройках.</p>'
            .'<p>После смены пары VAPID у части браузерных подписок может потребоваться повторная подписка.</p>'
        );
    }

    private function hasStoredTelegramToken(PlatformNotificationSettings $settings): bool
    {
        $t = $settings->telegramBotTokenDecrypted();

        return is_string($t) && $t !== '';
    }

    private function hasStoredVapidPair(PlatformNotificationSettings $settings): bool
    {
        return $settings->vapidPublicKey() !== null
            && $settings->vapidPrivateKeyDecrypted() !== null;
    }

    private function platformContactChatIdsContainAtPrefixedToken(string $raw): bool
    {
        if ($raw === '') {
            return false;
        }

        foreach (RecipientListParser::parse($raw) as $id) {
            if (str_starts_with($id, '@')) {
                return true;
            }
        }

        return false;
    }
}
