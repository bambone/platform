<?php

namespace App\Filament\Platform\Pages;

use App\ContactChannels\ContactChannelRegistry;
use App\ContactChannels\ContactChannelType;
use App\ContactChannels\PlatformMarketingContactChannelsStore;
use App\Filament\Platform\Pages\Concerns\GrantsPlatformPageAccess;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class PlatformMarketingContactChannelsPage extends Page
{
    use GrantsPlatformPageAccess;

    protected static ?string $navigationLabel = 'Каналы: форма сайта';

    protected static ?string $title = 'Маркетинг: каналы в форме контактов';

    protected static ?string $slug = 'marketing-contact-channels';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static ?int $navigationSort = 11;

    protected static ?string $panel = 'platform';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected string $view = 'filament.pages.platform.marketing-contact-channels';

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = app(PlatformMarketingContactChannelsStore::class)->resolvedState();
        $form = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $k = $type->value;
            $cfg = $state[$k];
            $form[$k.'_uses_channel'] = $cfg->usesChannel;
            $form[$k.'_allowed_in_forms'] = $cfg->allowedInForms;
            $form[$k.'_sort_order'] = (string) $cfg->sortOrder;
        }
        $this->data = $form;
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $k = $type->value;
            $label = ContactChannelRegistry::label($k);
            $sections[] = Section::make($label)
                ->description('Идентификатор: '.$k.'. На публичной форме маркетинга email всегда обязателен; здесь задаются дополнительные способы связи.')
                ->schema([
                    Toggle::make($k.'_uses_channel')
                        ->label('Канал используется')
                        ->helperText('Если выключено — канал не предлагается посетителю.'),
                    Toggle::make($k.'_allowed_in_forms')
                        ->label('Показывать в форме маркетингового сайта')
                        ->helperText('Посетитель сможет выбрать этот способ как предпочтительный (наряду с ответом на email).'),
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
        $data = $this->getSchema('form')->getState();
        $raw = [];
        foreach (ContactChannelType::allForTenantConfig() as $type) {
            $k = $type->value;
            $uses = ! empty($data[$k.'_uses_channel']);
            $inForm = ! empty($data[$k.'_allowed_in_forms']);
            $raw[$k] = [
                'uses_channel' => $uses,
                'public_visible' => false,
                'allowed_in_forms' => $inForm,
                'business_value' => '',
                'sort_order' => max(0, min(999, (int) ($data[$k.'_sort_order'] ?? 99))),
            ];
        }

        app(PlatformMarketingContactChannelsStore::class)->persist($raw);

        Notification::make()
            ->title('Каналы формы маркетинга сохранены')
            ->success()
            ->send();
    }
}
