<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use App\ContactChannels\ContactChannelRegistry;
use App\ContactChannels\ContactChannelType;
use App\ContactChannels\TenantContactChannelsStore;
use App\ContactChannels\TenantPublicSiteContactsService;
use App\Models\TenantSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ContactChannelsPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Каналы связи';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $title = 'Каналы связи';

    protected string $view = 'filament.pages.contact-channels';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage_settings') && currentTenant() !== null;
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'contactChannelsWhatIs',
                [
                    'Каналы связи: что использует команда, что показывается на сайте и в формах.',
                    '',
                    'Плавающие кнопки мессенджеров — отдельный переключатель вверху страницы.',
                ],
                'Справка по каналам связи',
            ),
        ];
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('manage_settings'), 403);
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $state = app(TenantContactChannelsStore::class)->resolvedState($tenant->id);
        $form = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $k = $type->value;
            $cfg = $state[$k];
            $form[$k.'_uses_channel'] = $cfg->usesChannel;
            $form[$k.'_public_visible'] = $cfg->publicVisible;
            $form[$k.'_allowed_in_forms'] = $cfg->allowedInForms;
            $form[$k.'_business_value'] = $cfg->businessValue;
            $form[$k.'_sort_order'] = (string) $cfg->sortOrder;
        }
        $form['floating_messenger_buttons_enabled'] = app(TenantPublicSiteContactsService::class)->floatingMessengerButtonsEnabled($tenant->id);
        $this->data = $form;
    }

    public function form(Schema $schema): Schema
    {
        $sections = [
            Section::make('Плавающие кнопки на лендинге')
                ->description('Круглые кнопки WhatsApp, Telegram и ВКонтакте в углу экрана на публичном сайте. Нужны: опция ниже включена, у канала включено «Команда использует этот канал», заполнен контакт и включено «Показывать на сайте» или «Разрешить в формах как предпочтительный канал».')
                ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                ->schema([
                    Toggle::make('floating_messenger_buttons_enabled')
                        ->label('Показывать плавающие кнопки мессенджеров')
                        ->default(true)
                        ->helperText('По умолчанию включено. Выключите, если не нужны поверх страницы.'),
                ])
                ->columns(1),
        ];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $k = $type->value;
            $label = ContactChannelRegistry::label($k);
            $sections[] = Section::make($label)
                ->description('Идентификатор: '.$k)
                ->schema([
                    Toggle::make($k.'_uses_channel')
                        ->label('Команда использует этот канал')
                        ->helperText('Влияет на быстрые действия в обращениях и приём ответов в этом канале.'),
                    Toggle::make($k.'_public_visible')
                        ->label('Показывать на сайте (контакт бизнеса)')
                        ->helperText('Для будущих блоков «связаться». Пустой контакт бизнеса не мешает приёму заявок в форме.'),
                    Toggle::make($k.'_allowed_in_forms')
                        ->label('Разрешить в формах как предпочтительный канал')
                        ->visible(function () use ($k): bool {
                            return $k !== ContactChannelType::Phone->value;
                        })
                        ->extraAttributes(['data-setup-target' => 'contact_channels.preferred_contact_channel'])
                        ->helperText('Посетитель сможет выбрать этот канал в модалке и checkout.'),
                    TextInput::make($k.'_business_value')
                        ->label('Контакт бизнеса (для сайта / справки)')
                        ->maxLength(500),
                    TextInput::make($k.'_sort_order')
                        ->label('Порядок сортировки')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(999),
                ])
                ->columns(2);
        }

        return $schema
            ->statePath('data')
            ->components($sections);
    }

    public function save(): void
    {
        abort_unless(Gate::allows('manage_settings'), 403);
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $data = $this->getSchema('form')->getState();

        TenantSetting::setForTenant(
            $tenant->id,
            'public_site.floating_messenger_buttons',
            ! empty($data['floating_messenger_buttons_enabled']),
            'boolean'
        );

        $raw = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $k = $type->value;
            $raw[$k] = [
                'uses_channel' => ! empty($data[$k.'_uses_channel']),
                'public_visible' => ! empty($data[$k.'_public_visible']),
                'allowed_in_forms' => $k === ContactChannelType::Phone->value
                    ? false
                    : ! empty($data[$k.'_allowed_in_forms']),
                'business_value' => trim((string) ($data[$k.'_business_value'] ?? '')),
                'sort_order' => max(0, min(999, (int) ($data[$k.'_sort_order'] ?? 99))),
            ];
        }

        app(TenantContactChannelsStore::class)->persist($tenant->id, $raw);

        Notification::make()
            ->title('Каналы связи сохранены')
            ->success()
            ->send();
    }
}
