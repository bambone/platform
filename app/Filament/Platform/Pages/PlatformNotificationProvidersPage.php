<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use App\Services\Platform\PlatformNotificationSettings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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

    public function mount(PlatformNotificationSettings $settings): void
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Каналы доставки')
                    ->description('Быстрое отключение канала на всей платформе. Выключенный канал не используется для новых уведомлений.')
                    ->schema([
                        Toggle::make('channel_email_enabled')->label('Электронная почта'),
                        Toggle::make('channel_telegram_enabled')->label('Telegram'),
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
                            ->helperText('Выдаётся в @BotFather. Оставьте пустым, чтобы не менять сохранённый токен. Чтобы удалить токен, включите «Сбросить токен» ниже.'),
                        Toggle::make('clear_telegram_bot_token')
                            ->label('Сбросить сохранённый токен')
                            ->helperText('Удаляет токен из настроек платформы. Новое значение в поле выше при этом игнорируется.')
                            ->default(false),
                        TextInput::make('platform_contact_chat_ids')
                            ->label('Chat ID для заявок с лендинга')
                            ->helperText('Один или несколько через запятую, либо JSON-массив строк. Для групп допускается отрицательный id.')
                            ->maxLength(4000),
                    ]),
                Section::make('Web Push (VAPID)')
                    ->schema([
                        TextInput::make('vapid_public')
                            ->label('Публичный ключ')
                            ->helperText('Смена публичного ключа требует ввода пары: укажите и приватный ключ. Иначе включите сброс и задайте пару заново.'),
                        TextInput::make('vapid_private')
                            ->label('Приватный ключ')
                            ->password()
                            ->revealable()
                            ->helperText('Оставьте пустым, чтобы не менять сохранённый приватный ключ (если публичный не меняли).'),
                        Toggle::make('clear_vapid_keys')
                            ->label('Сбросить ключи VAPID')
                            ->helperText('Удаляет оба ключа из настроек. Поля выше при включённом сбросе не применяются.')
                            ->default(false),
                    ]),
            ]);
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

        $settings->setChannelEnabled('email', (bool) ($data['channel_email_enabled'] ?? false));
        $settings->setChannelEnabled('telegram', (bool) ($data['channel_telegram_enabled'] ?? false));
        $settings->setChannelEnabled('webhook', (bool) ($data['channel_webhook_enabled'] ?? false));
        $settings->setChannelEnabled('web_push', (bool) ($data['channel_web_push_enabled'] ?? false));
        $settings->setChannelEnabled('web_push_onesignal', (bool) ($data['channel_web_push_onesignal_enabled'] ?? false));

        if ((bool) ($data['clear_telegram_bot_token'] ?? false)) {
            $settings->setTelegramBotToken(null);
        } else {
            $token = trim((string) ($data['telegram_bot_token'] ?? ''));
            if ($token !== '') {
                $settings->setTelegramBotToken($token);
            }
        }

        $chatIdsRaw = trim((string) ($data['platform_contact_chat_ids'] ?? ''));
        $settings->setPlatformContactTelegramChatIds($chatIdsRaw !== '' ? $chatIdsRaw : null);

        if ($clearVapid) {
            $settings->clearVapidKeys();
        } elseif ($pub !== '' && $priv !== '') {
            $settings->setVapidKeypair($pub, $priv);
        }

        Notification::make()->title('Сохранено')->success()->send();

        $fresh = app(PlatformNotificationSettings::class);
        $next = $this->getSchema('form')->getState();
        $next['telegram_bot_token'] = '';
        $next['clear_telegram_bot_token'] = false;
        $next['clear_vapid_keys'] = false;
        $next['vapid_private'] = '';
        $next['vapid_public'] = $fresh->vapidPublicKey() ?? '';
        $this->getSchema('form')->fill($next);
    }
}
