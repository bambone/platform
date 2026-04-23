<?php

namespace App\PageBuilder\Blueprints;

use App\PageBuilder\Contacts\ContactMapPreviewBuilder;
use App\PageBuilder\Contacts\ContactMapSourceParser;
use App\PageBuilder\Contacts\MapDisplayMode;
use App\PageBuilder\Contacts\MapInputMode;
use App\PageBuilder\Contacts\MapProvider;
use App\PageBuilder\PageSectionCategory;
use App\Support\RussianPhone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class ContactsBlueprint extends AbstractPageSectionBlueprint
{
    public function supportsTheme(string $themeKey): bool
    {
        return in_array($themeKey, ['default', 'moto', 'expert_auto', 'advocate_editorial', 'black_duck'], true);
    }

    public function id(): string
    {
        return 'contacts';
    }

    public function label(): string
    {
        return 'Контакты';
    }

    public function description(): string
    {
        return 'Телефон, мессенджеры, адрес, карта.';
    }

    public function icon(): string
    {
        return 'heroicon-o-phone';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Contacts;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'description' => '',
            'phone' => '',
            'email' => '',
            'whatsapp' => '',
            'telegram' => '',
            'vk_url' => '',
            'address' => '',
            'map_enabled' => true,
            'map_provider' => MapProvider::Yandex->value,
            'map_public_url' => '',
            'map_combined_input' => '',
            'map_secondary_combined_input' => '',
            'map_input_mode' => MapInputMode::Auto->value,
            'map_display_mode' => MapDisplayMode::EmbedAndButton->value,
            'map_title' => '',
            'social_note' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('data_json.description')
                ->label('Описание')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('data_json.phone')
                ->label('Телефон')
                ->tel()
                ->telRegex(RussianPhone::filamentTelDisplayRegex())
                ->maxLength(64),
            TextInput::make('data_json.email')
                ->label('Email')
                ->email()
                ->maxLength(255),
            TextInput::make('data_json.whatsapp')
                ->label('WhatsApp (ссылка или номер)')
                ->maxLength(255),
            TextInput::make('data_json.telegram')
                ->label('Telegram (username или ссылка)')
                ->maxLength(255),
            TextInput::make('data_json.vk_url')
                ->label('ВКонтакте (ссылка на профиль или id)')
                ->maxLength(500)
                ->placeholder('https://vk.com/id123456 или короткое имя'),
            Textarea::make('data_json.address')
                ->label('Адрес')
                ->rows(2)
                ->columnSpanFull(),
            Section::make('Карта')
                ->description('Предпросмотр обновляется при изменении ссылки и режима.')
                ->schema([
                    Toggle::make('data_json.map_enabled')
                        ->label('Показывать карту')
                        ->default(true)
                        ->live(),
                    Select::make('data_json.map_provider')
                        ->label('Провайдер карты')
                        ->options([
                            MapProvider::None->value => 'Не выбрано',
                            MapProvider::Yandex->value => 'Яндекс Карты',
                            MapProvider::Google->value => 'Google Maps',
                            MapProvider::TwoGis->value => '2ГИС',
                        ])
                        ->native(true)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === MapProvider::TwoGis->value) {
                                $set('data_json.map_display_mode', MapDisplayMode::ButtonOnly->value);
                            }
                        })
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled') ?? false)),
                    Textarea::make('data_json.map_combined_input')
                        ->label('Вставьте ссылку или код карты')
                        ->maxLength(ContactMapSourceParser::COMBINED_INPUT_MAX_LENGTH)
                        ->rows(3)
                        ->placeholder(fn (Get $get): string => match (MapProvider::tryFromMixed($get('data_json.map_provider') ?? '') ?? MapProvider::None) {
                            MapProvider::Yandex => 'https://yandex.ru/maps/?ll=… или код iframe',
                            MapProvider::Google => 'https://www.google.com/maps/… или код iframe',
                            MapProvider::TwoGis => 'https://2gis.ru/… (ссылка из «Поделиться»)',
                            MapProvider::None => 'https://…',
                        })
                        ->helperText(function (Get $get): string {
                            $p = MapProvider::tryFromMixed($get('data_json.map_provider') ?? '') ?? MapProvider::None;
                            if ($p === MapProvider::TwoGis) {
                                return 'Вставьте ссылку из «Поделиться». Окно 2ГИС обычно даёт ссылку, а не готовый iframe для сайта — на странице будет кнопка «Открыть в 2ГИС». Встраивание карты через API/виджет — отдельная интеграция.';
                            }

                            return 'Можно вставить ссылку или код iframe из Яндекс Карт, Google Maps или 2ГИС. Сайт не использует этот код напрямую — система сама безопасно извлечёт данные. Система сама определит формат.';
                        })
                        ->live()
                        ->extraInputAttributes([
                            'class' => 'fi-map-combined-input',
                        ])
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            if (trim((string) $state) === '') {
                                $set('data_json.map_public_url', '');
                            }
                            $data = $get('data_json');
                            $data = is_array($data) ? $data : [];
                            $next = ContactMapSourceParser::maybeBumpDisplayModeForIframePaste($data, $state);
                            if ($next !== null) {
                                $set('data_json.map_display_mode', $next);
                            }
                        })
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? MapProvider::None->value) !== MapProvider::None->value)
                        ->rules([
                            function (Get $get): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $data = $get('data_json');
                                    $data = is_array($data) ? $data : [];
                                    $parse = ContactMapSourceParser::parseFromDataJson($data);
                                    if ($parse->isEmpty) {
                                        return;
                                    }
                                    if (! $parse->ok) {
                                        $fail($parse->errors[0] ?? 'Не удалось разобрать ввод.');
                                    }
                                };
                            },
                        ]),
                    Textarea::make('data_json.map_secondary_combined_input')
                        ->label('Дополнительная ссылка на карту (опционально)')
                        ->maxLength(ContactMapSourceParser::COMBINED_INPUT_MAX_LENGTH)
                        ->rows(2)
                        ->placeholder('Например, ссылка 2ГИС или второй сервис — отдельной кнопкой рядом с основной.')
                        ->helperText('Можно комбинировать: встроенная Яндекс/Google и кнопка 2ГИС, две кнопки на разные сервисы и т.д. Вставьте обычную ссылку или код iframe — как в основном поле.')
                        ->live()
                        ->extraInputAttributes([
                            'class' => 'fi-map-secondary-combined-input',
                        ])
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if (trim((string) $state) === '') {
                                $set('data_json.map_secondary_public_url', '');
                            }
                        })
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? MapProvider::None->value) !== MapProvider::None->value)
                        ->rules([
                            function (Get $get): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail): void {
                                    $raw = trim((string) $value);
                                    if ($raw === '') {
                                        return;
                                    }
                                    $parse = ContactMapSourceParser::parse(MapInputMode::Auto, $raw, null);
                                    if (! $parse->ok && ! $parse->isEmpty) {
                                        $fail($parse->errors[0] ?? 'Не удалось разобрать дополнительную ссылку.');
                                    }
                                };
                            },
                        ]),
                    Section::make('Расширенные настройки карты')
                        ->collapsed()
                        ->schema([
                            Select::make('data_json.map_input_mode')
                                ->label('Как интерпретировать ввод')
                                ->options([
                                    MapInputMode::Auto->value => 'Авто (рекомендуется)',
                                    MapInputMode::Url->value => 'Только ссылка',
                                    MapInputMode::Iframe->value => 'Только код карты',
                                ])
                                ->native(true)
                                ->live()
                                ->default(MapInputMode::Auto->value),
                        ])
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? '') !== MapProvider::None->value)
                        ->columnSpanFull(),
                    Select::make('data_json.map_display_mode')
                        ->label('Как показывать')
                        ->options([
                            MapDisplayMode::ButtonOnly->value => 'Только ссылка (кнопка)',
                            MapDisplayMode::EmbedOnly->value => 'Только карта',
                            MapDisplayMode::EmbedAndButton->value => 'Карта и кнопка',
                        ])
                        ->native(true)
                        ->live()
                        ->helperText(function (Get $get): string {
                            $p = MapProvider::tryFromMixed($get('data_json.map_provider') ?? '') ?? MapProvider::None;
                            if ($p === MapProvider::TwoGis) {
                                return 'Для 2ГИС по умолчанию — «Только ссылка»: встроенная карта из обычной ссылки «Поделиться» не поддерживается. Режимы с iframe — только если у вас ссылка на embed.2gis.com.';
                            }

                            return 'Выберите, как карта будет показана на сайте. В режиме «Только ссылка» iframe не отображается — даже если вы вставили код виджета.';
                        })
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? '') !== MapProvider::None->value),
                    TextInput::make('data_json.map_title')
                        ->label('Заголовок блока карты')
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? '') !== MapProvider::None->value),
                    ViewField::make('contact_map_status_preview_contacts')
                        ->hiddenLabel()
                        ->view('filament.tenant.page-builder.contact-map-editor-panel')
                        ->viewData(fn (Get $get): array => [
                            'preview' => ContactMapPreviewBuilder::fromGet($get),
                        ])
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? '') !== MapProvider::None->value)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->collapsible(),
            TextInput::make('data_json.social_note')
                ->label('Соцсети (текстом)')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.contacts';
    }

    public function previewSummary(array $data): string
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $vk = trim((string) ($data['vk_url'] ?? ''));
        $addr = $this->stringPreview($data, 'address', 50);

        return trim(implode(' · ', array_filter([$phone, $email, $vk, $addr], fn (string $s): bool => $s !== ''))) ?: 'Контакты не заполнены';
    }
}
