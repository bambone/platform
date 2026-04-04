<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Models\PageSection;
use App\PageBuilder\Contacts\ContactChannelRegistry;
use App\PageBuilder\Contacts\ContactChannelsResolver;
use App\PageBuilder\Contacts\ContactChannelType;
use App\PageBuilder\PageSectionCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

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
            'map_embed' => null,
            'map_link' => null,
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
            Textarea::make('data_json.map_embed')
                ->label('Карта (HTML iframe, опционально)')
                ->helperText('Код вставки с Яндекс.Карт, Google Maps и т.п. Если пусто — можно указать только ссылку ниже.')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('data_json.map_link')
                ->label('Ссылка на карту')
                ->url()
                ->maxLength(2048)
                ->helperText('Откроется в новой вкладке; при отсутствии iframe некоторые ссылки можно подставить как src (если сервис разрешает).'),
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
        $mapEmbed = trim((string) ($data['map_embed'] ?? ''));
        $mapLink = trim((string) ($data['map_link'] ?? ''));
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
        if ($mapEmbed !== '' || $mapLink !== '') {
            $lines[] = 'Карта заполнена';
        }

        $hasMeta = $addr !== '' || $hours !== '' || $mapEmbed !== '' || $mapLink !== '';
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
