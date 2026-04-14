<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Models\PageSection;
use App\PageBuilder\Contacts\ContactChannelRegistry;
use App\PageBuilder\Contacts\ContactChannelsResolver;
use App\PageBuilder\Contacts\ContactChannelType;
use App\PageBuilder\Contacts\ContactMapPreviewBuilder;
use App\PageBuilder\Contacts\ContactMapSourceParser;
use App\PageBuilder\Contacts\MapDisplayMode;
use App\PageBuilder\Contacts\MapInputMode;
use App\PageBuilder\Contacts\MapProvider;
use App\PageBuilder\PageSectionCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class ContactsInfoSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'contacts_info';
    }

    public function label(): string
    {
        return 'Контакты';
    }

    public function description(): string
    {
        return 'Каналы связи, адрес, режим работы и карта. Каналы настраиваются списком; пустые и выключенные не показываются на сайте.';
    }

    public function icon(): string
    {
        return 'heroicon-o-map-pin';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Contacts;
    }

    public function defaultData(): array
    {
        return [
            'title' => 'Контакты',
            'description' => null,
            'additional_note' => null,
            'channels' => [],
            'phone' => null,
            'email' => null,
            'whatsapp' => null,
            'telegram' => null,
            'address' => null,
            'working_hours' => null,
            'map_enabled' => true,
            'map_provider' => MapProvider::Yandex->value,
            'map_public_url' => '',
            'map_combined_input' => '',
            'map_secondary_combined_input' => '',
            'map_input_mode' => MapInputMode::Auto->value,
            'map_display_mode' => MapDisplayMode::EmbedAndButton->value,
            'map_title' => '',
        ];
    }

    public function formComponents(): array
    {
        $registry = app(ContactChannelRegistry::class);
        $resolver = app(ContactChannelsResolver::class);

        return [
            TextInput::make('data_json.title')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('data_json.description')
                ->label('Короткий текст под заголовком')
                ->helperText('Например, когда лучше звонить или как добраться до точки выдачи.')
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('data_json.additional_note')
                ->label('Дополнительная заметка / перед визитом')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('data_json.address')
                ->label('Адрес')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('data_json.working_hours')
                ->label('Режим работы')
                ->rows(2)
                ->columnSpanFull(),
            Section::make('Карта')
                ->description('Настройки карты и предпросмотр. Пустую ссылку можно сохранить — на сайте карта появится после валидной ссылки.')
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
                                return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
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

                            return 'Выберите, как карта будет показана на сайте: только кнопка во внешнем сервисе, встроенная карта или оба варианта. В режиме «Только ссылка» iframe не отображается — даже если вы вставили код виджета.';
                        })
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? '') !== MapProvider::None->value),
                    Placeholder::make('map_provider_examples')
                        ->label('')
                        ->content(function (Get $get): string {
                            $p = MapProvider::tryFromMixed($get('data_json.map_provider') ?? '') ?? MapProvider::None;

                            return match ($p) {
                                MapProvider::Yandex => 'Примеры Яндекс: «подойдёт для встраивания» — ссылка с параметрами ll и z на карте или готовый виджет yandex.ru/map-widget/…; «часто только кнопка» — обычная ссылка поиска maps/?text=… без координат.',
                                MapProvider::Google => 'Google: встроенная карта поддерживается для ссылок с /maps/embed/…; обычная ссылка «Поделиться» чаще открывается кнопкой.',
                                MapProvider::TwoGis => '2ГИС: в окне «Поделиться» вы получаете ссылку, а не готовый iframe для сайта. На странице будет кнопка «Открыть в 2ГИС». Полноценное встраивание — через embed.2gis.com или отдельную dev-интеграцию (API/виджет).',
                                default => '',
                            };
                        })
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && in_array((string) ($get('data_json.map_provider') ?? ''), [
                            MapProvider::Yandex->value,
                            MapProvider::Google->value,
                            MapProvider::TwoGis->value,
                        ], true))
                        ->columnSpanFull(),
                    TextInput::make('data_json.map_title')
                        ->label('Заголовок блока карты')
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->helperText('Необязательно. Если пусто — на сайте будет общий заголовок блока.')
                        ->visible(fn (Get $get): bool => (bool) ($get('data_json.map_enabled')) && ($get('data_json.map_provider') ?? '') !== MapProvider::None->value),
                    ViewField::make('contact_map_status_preview')
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
            Section::make('Каналы связи')
                ->schema([
                    Placeholder::make('channels_repeater_hint')
                        ->label('')
                        ->content(function (Get $get) use ($resolver): string {
                            $data = $get('data_json');
                            $data = is_array($data) ? $data : [];
                            $channels = $data['channels'] ?? [];
                            if (! is_array($channels) || $channels === []) {
                                return 'Добавьте каналы связи. Пока список пуст, на сайте могут использоваться устаревшие поля (если они были заполнены раньше), пока вы не сохраните блок.';
                            }
                            $analysis = $resolver->analyze($data);
                            if ($analysis->enabledCount > 0 && $analysis->usableCount === 0) {
                                return 'Все включённые каналы сейчас не отображаются на сайте — исправьте значения или выключите лишнее.';
                            }

                            return '';
                        })
                        ->columnSpanFull(),
                    Repeater::make('data_json.channels')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('type')
                                ->label('Тип')
                                ->options($registry->selectOptionsGrouped())
                                ->required()
                                ->native(true)
                                ->live()
                                ->columnSpanFull(),
                            Toggle::make('is_enabled')
                                ->label('Включён')
                                ->default(true)
                                ->live(),
                            Toggle::make('is_primary')
                                ->label('Основной (крупнее на сайте)')
                                ->default(false)
                                ->live(),
                            TextInput::make('value')
                                ->label('Значение')
                                ->maxLength(2048)
                                ->live(onBlur: true)
                                ->required(fn (Get $get): bool => (bool) ($get('is_enabled') ?? true))
                                ->helperText(fn (Get $get): ?string => $this->helperForType($registry, $get('type')))
                                ->columnSpanFull(),
                            Section::make('Расширенные')
                                ->description('Подпись, CTA, открытие ссылки, ручной URL, предпросмотр и подсказки по строке.')
                                ->collapsed()
                                ->schema([
                                    TextInput::make('label')
                                        ->label('Подпись на сайте')
                                        ->maxLength(255)
                                        ->helperText('Необязательно: переопределить название канала. Для «Ссылка / другой канал» обязательно.')
                                        ->required(fn (Get $get): bool => ($get('type') ?? '') === ContactChannelType::GenericUrl->value && (bool) ($get('is_enabled') ?? true)),
                                    TextInput::make('cta_label')
                                        ->label('Текст кнопки / CTA')
                                        ->maxLength(120)
                                        ->helperText('Пусто — подставится текст по умолчанию для типа.'),
                                    TextInput::make('note')
                                        ->label('Подсказка под ссылкой')
                                        ->maxLength(500),
                                    Select::make('open_in_new_tab')
                                        ->label('Открытие ссылки')
                                        ->options([
                                            'inherit' => 'По умолчанию для типа',
                                            '1' => 'Всегда новая вкладка',
                                            '0' => 'Всегда эта вкладка',
                                        ])
                                        ->default('inherit')
                                        ->formatStateUsing(function ($state): string {
                                            if ($state === null || $state === '' || $state === 'inherit') {
                                                return 'inherit';
                                            }
                                            if ($state === true || $state === 1 || $state === '1') {
                                                return '1';
                                            }

                                            return '0';
                                        })
                                        ->native(true),
                                    Toggle::make('is_override_url')
                                        ->label('Ручная ссылка')
                                        ->helperText('Включите только если нужно задать href вручную (редко).')
                                        ->default(false)
                                        ->live(),
                                    TextInput::make('url')
                                        ->label('URL вручную')
                                        ->maxLength(2048)
                                        ->visible(fn (Get $get): bool => (bool) ($get('is_override_url') ?? false))
                                        ->live(onBlur: true),
                                    Placeholder::make('href_preview')
                                        ->label('Итоговая ссылка (предпросмотр)')
                                        ->content(function (Get $get) use ($resolver): string {
                                            $row = [
                                                'type' => $get('type'),
                                                'value' => $get('value'),
                                                'url' => $get('url'),
                                                'is_override_url' => $get('is_override_url'),
                                                'is_enabled' => $get('is_enabled'),
                                                'is_primary' => $get('is_primary'),
                                                'label' => $get('label'),
                                                'cta_label' => $get('cta_label'),
                                                'note' => $get('note'),
                                                'open_in_new_tab' => $get('open_in_new_tab'),
                                            ];
                                            $href = $resolver->previewHrefForRow($row);

                                            return $href ?? '— (не собрана)';
                                        })
                                        ->columnSpanFull(),
                                    Placeholder::make('row_issues')
                                        ->hiddenLabel()
                                        ->content(function (Get $get) use ($resolver): string {
                                            if (! (bool) ($get('is_enabled') ?? true)) {
                                                return '';
                                            }
                                            $row = [
                                                'type' => $get('type'),
                                                'value' => $get('value'),
                                                'url' => $get('url'),
                                                'is_override_url' => $get('is_override_url'),
                                                'is_enabled' => $get('is_enabled'),
                                                'is_primary' => $get('is_primary'),
                                                'label' => $get('label'),
                                                'cta_label' => $get('cta_label'),
                                                'note' => $get('note'),
                                                'open_in_new_tab' => $get('open_in_new_tab'),
                                            ];
                                            $issues = $this->rowWarnings($resolver, $row);
                                            if ($issues === []) {
                                                if ((bool) ($get('is_primary') ?? false) && $resolver->previewHrefForRow($row) === null) {
                                                    return 'Внимание: основной канал не отобразится, пока значение или ссылка не станут валидными.';
                                                }

                                                return '';
                                            }

                                            return 'Внимание: '.implode(' ', $issues);
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->defaultItems(0)
                        // Partial HTML patches after add/reorder often fail to morph new <li> rows in Livewire; full render is safer.
                        ->partiallyRenderAfterActionsCalled(false)
                        // Without this, Filament may still dehydrate partial-only effects; editor is @teleport('body') — force full HTML.
                        ->addAction(fn (Action $action): Action => $this->withForceFullLivewireRenderAfterRepeaterAction($action))
                        ->addBetweenAction(fn (Action $action): Action => $this->withForceFullLivewireRenderAfterRepeaterAction($action))
                        ->deleteAction(fn (Action $action): Action => $this->withForceFullLivewireRenderAfterRepeaterAction($action))
                        ->moveUpAction(fn (Action $action): Action => $this->withForceFullLivewireRenderAfterRepeaterAction($action))
                        ->moveDownAction(fn (Action $action): Action => $this->withForceFullLivewireRenderAfterRepeaterAction($action))
                        ->cloneAction(fn (Action $action): Action => $this->withForceFullLivewireRenderAfterRepeaterAction($action))
                        ->reorderable()
                        ->reorderableWithDragAndDrop(false)
                        ->reorderableWithButtons(true)
                        ->addActionLabel('Добавить канал')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Ensures the host Livewire component sends a full snapshot (not partial patches) after repeater mutations.
     * Fixes missing new rows when the section editor is teleported to <body>.
     */
    private function withForceFullLivewireRenderAfterRepeaterAction(Action $action): Action
    {
        return $action->after(function () use ($action): void {
            $livewire = $action->getLivewire();
            if ($livewire !== null && method_exists($livewire, 'forceRender')) {
                $livewire->forceRender();
            }
        });
    }

    /**
     * @return list<string>
     */
    private function rowWarnings(ContactChannelsResolver $resolver, array $row): array
    {
        $data = ['channels' => [$row]];
        $analysis = $resolver->analyze($data);

        return $analysis->rowIssues[0] ?? [];
    }

    private function helperForType(ContactChannelRegistry $registry, ?string $type): ?string
    {
        $t = ContactChannelType::tryFromMixed($type ?? '');

        return $t ? $registry->valueHelperText($t) : null;
    }

    public function viewLogicalName(): string
    {
        return 'sections.contacts-info';
    }

    public function previewSummary(array $data): string
    {
        $resolver = app(ContactChannelsResolver::class);
        $analysis = $resolver->analyze($data);
        $parts = [];
        if ($analysis->usableCount > 0) {
            $parts[] = $analysis->usableCount.' '.self::pluralActiveChannels($analysis->usableCount);
        } else {
            $parts[] = 'нет активных каналов';
        }
        if ($analysis->hasAddress) {
            $parts[] = 'адрес';
        }
        if ($analysis->hasHours) {
            $parts[] = 'часы';
        }
        if ($analysis->hasMap) {
            $parts[] = 'карта';
        }
        if ($analysis->usablePrimaryCount === 0 && $analysis->usableCount > 0) {
            $parts[] = 'без основного';
        }

        return implode(' · ', $parts);
    }

    public function adminSummary(PageSection $section): SectionAdminSummary
    {
        $data = is_array($section->data_json) ? $section->data_json : [];
        $label = $this->label();
        $listTitle = trim((string) ($section->title ?? ''));
        $blockTitle = trim((string) ($data['title'] ?? ''));
        $displayTitle = $listTitle !== '' ? $listTitle : ($blockTitle !== '' ? $blockTitle : $label);
        $key = trim((string) ($section->section_key ?? ''));
        $displaySubtitle = $key !== '' ? $key.' · '.$label : $label;
        $primaryHeadline = $blockTitle !== '' ? $blockTitle : null;

        $resolver = app(ContactChannelsResolver::class);
        $registry = app(ContactChannelRegistry::class);
        $analysis = $resolver->analyze($data);

        $addr = trim((string) ($data['address'] ?? ''));
        $hours = trim((string) ($data['working_hours'] ?? ''));
        $lines = [];
        if ($analysis->usableCount > 0) {
            $lines[] = $analysis->usableCount.' '.self::pluralActiveChannels($analysis->usableCount);
            if ($analysis->usablePrimaryCount > 0) {
                $lines[] = 'Основных: '.$analysis->usablePrimaryCount;
            }
        }
        if ($addr !== '') {
            $lines[] = 'Адрес: '.$this->stringPreview($data, 'address', 140);
        }
        if ($hours !== '') {
            $lines[] = 'Часы: '.$this->stringPreview($data, 'working_hours', 100);
        }
        if ($analysis->hasMap) {
            $lines[] = 'Карта заполнена';
        }

        $hasMeta = $addr !== '' || $hours !== '' || $analysis->hasMap;
        $isEmpty = $analysis->usableCount === 0 && ! $hasMeta && trim((string) ($data['description'] ?? '')) === '' && trim((string) ($data['additional_note'] ?? '')) === '';

        $warning = $analysis->warnings[0] ?? null;
        if ($isEmpty) {
            $warning = 'Нет активных каналов, адреса и карты';
        } elseif ($analysis->usableCount === 0 && $addr === '') {
            $warning = $warning ?? 'Нет активных каналов и пустой адрес';
        }

        $channels = $this->channelHintsForSummary($data, $registry, $resolver);

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: $lines !== [] ? $lines : ($isEmpty ? ['Каналы не заполнены'] : []),
            badges: [],
            meta: [],
            isEmpty: $isEmpty,
            warning: $warning,
            primaryHeadline: $primaryHeadline,
            channels: $channels,
        );
    }

    /**
     * @return list<array{icon: string, label: string, on: bool, primary?: bool}>
     */
    private function channelHintsForSummary(array $data, ContactChannelRegistry $registry, ContactChannelsResolver $resolver): array
    {
        $raw = $data['channels'] ?? [];
        if (is_array($raw) && $raw !== []) {
            $hints = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $type = ContactChannelType::tryFromMixed($row['type'] ?? '');
                if ($type === null) {
                    continue;
                }
                $usable = $resolver->previewHrefForRow($row) !== null;
                $hints[] = [
                    'icon' => $registry->filamentIcon($type),
                    'label' => $registry->label($type),
                    'on' => $usable,
                    'primary' => (bool) ($row['is_primary'] ?? false),
                ];
            }

            return $hints;
        }

        $presentation = $resolver->present($data);
        $hints = [];
        foreach ($presentation->allUsableChannels() as $ch) {
            $hints[] = [
                'icon' => $registry->filamentIcon($ch->type),
                'label' => $ch->type->value,
                'on' => true,
                'primary' => false,
            ];
        }

        return $hints;
    }

    private static function pluralActiveChannels(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return 'активных каналов';
        }
        if ($n1 > 1 && $n1 < 5) {
            return 'активных канала';
        }
        if ($n1 === 1) {
            return 'активный канал';
        }

        return 'активных каналов';
    }
}
