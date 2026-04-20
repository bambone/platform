<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\MotorcycleResource\Form;

use App\Enums\MotorcycleLocationMode;
use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Forms\Components\TenantSpatieMediaLibraryFileUpload;
use App\Filament\Support\HintIconTooltip;
use App\Models\Motorcycle;
use App\Models\TenantLocation;
use App\MotorcyclePricing\ApplicabilityMode;
use App\MotorcyclePricing\MotorcyclePricingProfileFormHydrator;
use App\MotorcyclePricing\MotorcyclePricingProfileLoader;
use App\MotorcyclePricing\MotorcyclePricingProfileValidator;
use App\MotorcyclePricing\MotorcyclePricingSchema;
use App\MotorcyclePricing\TariffCatalogDayUnit;
use App\MotorcyclePricing\TariffKind;
use App\Services\Seo\TenantSeoPublicPreviewService;
use App\Support\CatalogHighlightNormalizer;
use App\Support\Motorcycle\MotorcycleMediaPersistence;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Переиспользуемые «кирпичи» полей мотоцикла для Create (resource form) и Livewire block editors на Edit.
 */
final class MotorcycleFormFieldKit
{
    /**
     * @return array<int, Component>
     */
    public static function mainInfoFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Название')
                ->id('motorcycle-name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                    if ($operation === 'create' && $state) {
                        $set('slug', Str::slug($state));
                    }
                }),
            TextInput::make('slug')
                ->label('URL-идентификатор')
                ->id('motorcycle-slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Латиница, цифры и дефис; URL карточки в каталоге.'),
            TextInput::make('brand')
                ->label('Бренд')
                ->id('motorcycle-brand')
                ->maxLength(255),
            TextInput::make('model')
                ->label('Модель')
                ->id('motorcycle-model')
                ->maxLength(255),
            Textarea::make('short_description')
                ->label('Позиционирование в каталоге')
                ->id('motorcycle-short-description')
                ->rows(3)
                ->helperText('1–2 строки: сценарий и отличия от соседних моделей в каталоге.')
                ->columnSpanFull(),
            TextInput::make('catalog_scenario')
                ->label('Сценарий / кому подойдёт')
                ->id('motorcycle-catalog-scenario')
                ->maxLength(120)
                ->placeholder('Например: Туристу и трассе')
                ->columnSpanFull(),
            Fieldset::make('Быстрые преимущества (чипы в каталоге, словарь на сайте)')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('catalog_highlight_1')
                                ->label('Чип 1')
                                ->id('motorcycle-catalog-highlight-1')
                                ->placeholder('—')
                                ->formatStateUsing(fn (?string $state): ?string => CatalogHighlightNormalizer::normalizeToKey($state))
                                ->options(fn (): array => CatalogHighlightNormalizer::selectOptions()),
                            Select::make('catalog_highlight_2')
                                ->label('Чип 2')
                                ->id('motorcycle-catalog-highlight-2')
                                ->placeholder('—')
                                ->formatStateUsing(fn (?string $state): ?string => CatalogHighlightNormalizer::normalizeToKey($state))
                                ->options(fn (): array => CatalogHighlightNormalizer::selectOptions()),
                            Select::make('catalog_highlight_3')
                                ->label('Чип 3')
                                ->id('motorcycle-catalog-highlight-3')
                                ->placeholder('—')
                                ->formatStateUsing(fn (?string $state): ?string => CatalogHighlightNormalizer::normalizeToKey($state))
                                ->options(fn (): array => CatalogHighlightNormalizer::selectOptions()),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function pageModelFields(): array
    {
        return [
            Textarea::make('detail_audience')
                ->label('Кому подойдёт')
                ->id('motorcycle-detail-audience')
                ->rows(3)
                ->helperText('Если пусто — подставится сценарий из блока «Основное».')
                ->columnSpanFull(),
            Textarea::make('detail_use_case_bullets')
                ->label('Сценарий: тезисы (по одному на строку, до 4)')
                ->id('motorcycle-detail-use-case')
                ->rows(5)
                ->formatStateUsing(function ($state): string {
                    if (is_array($state)) {
                        return implode("\n", array_filter($state, 'filled'));
                    }

                    return '';
                })
                ->dehydrateStateUsing(function (?string $state): array {
                    if ($state === null || trim($state) === '') {
                        return [];
                    }
                    $lines = preg_split('/\r\n|\r|\n/', $state) ?: [];
                    $lines = array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));

                    return array_slice($lines, 0, 4);
                })
                ->columnSpanFull(),
            Textarea::make('detail_advantage_bullets')
                ->label('Ключевые плюсы (по одному на строку, до 6)')
                ->id('motorcycle-detail-advantages')
                ->rows(6)
                ->formatStateUsing(function ($state): string {
                    if (is_array($state)) {
                        return implode("\n", array_filter($state, 'filled'));
                    }

                    return '';
                })
                ->dehydrateStateUsing(function (?string $state): array {
                    if ($state === null || trim($state) === '') {
                        return [];
                    }
                    $lines = preg_split('/\r\n|\r|\n/', $state) ?: [];
                    $lines = array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));

                    return array_slice($lines, 0, 6);
                })
                ->columnSpanFull(),
            Section::make('Аренда: примечания к модели')
                ->collapsed()
                ->schema([
                    Textarea::make('detail_rental_notes')
                        ->label('Текст')
                        ->id('motorcycle-detail-rental')
                        ->rows(4)
                        ->helperText('Проверяемые формулировки; общие условия — на странице «Правила аренды».')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function specsSections(): array
    {
        return [
            Section::make('Базовые параметры')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('engine_cc')
                                ->label('Объём двигателя')
                                ->id('motorcycle-engine-cc')
                                ->numeric()
                                ->suffix('см³'),
                            TextInput::make('power')
                                ->label('Мощность')
                                ->id('motorcycle-power')
                                ->numeric()
                                ->suffix('л.с.'),
                            TextInput::make('transmission')
                                ->label('Трансмиссия')
                                ->id('motorcycle-transmission')
                                ->maxLength(255),
                            TextInput::make('year')
                                ->label('Год выпуска')
                                ->id('motorcycle-year')
                                ->numeric(),
                            TextInput::make('mileage')
                                ->label('Пробег')
                                ->id('motorcycle-mileage')
                                ->numeric()
                                ->suffix('км'),
                        ]),
                ])
                ->columns(1)
                ->compact()
                ->secondary(),
            Section::make('Дополнительные характеристики (расширенный режим)')
                ->description('Пары «название — значение» для редких полей. Основные поля выше предпочтительнее. Не используйте без необходимости — опечатки в ключах не попадут на сайт автоматически.')
                ->schema([
                    KeyValue::make('specs_json')
                        ->label('Произвольные параметры')
                        ->id('motorcycle-specs-json')
                        ->keyLabel('Название')
                        ->valueLabel('Значение')
                        ->reorderable(),
                ])
                ->columns(1)
                ->compact()
                ->secondary()
                ->collapsed()
                ->collapsible(),
        ];
    }

    /**
     * @return array<int, RichEditor>
     */
    public static function fullDescriptionField(): array
    {
        return [
            RichEditor::make('full_description')
                ->label('Текст для сайта')
                ->placeholder('Заголовки, абзацы, списки. Не дублируйте короткие тезисы — они задаются отдельным блоком выше.')
                ->helperText('Узкая колонка и высота поля — чтобы было удобнее читать и править длинный текст.')
                ->id('motorcycle-full-description')
                ->columnSpanFull(),
        ];
    }

    public static function seoSnippetPreviewPlaceholder(): TextEntry
    {
        return TextEntry::make('seo_resolver_preview')
            ->label('Публичный title / description (как у TenantSeoResolver)')
            ->state(function (?Motorcycle $record): HtmlString {
                if ($record === null || ! $record->exists) {
                    return new HtmlString('<p class="text-sm text-gray-500">Сохраните запись, чтобы увидеть предпросмотр.</p>');
                }
                $tenant = tenant();
                if ($tenant === null) {
                    return new HtmlString('');
                }
                $snippet = app(TenantSeoPublicPreviewService::class)->motorcycleSnippet($tenant, $record->fresh(['seoMeta', 'category']));
                $t = e($snippet['title']);
                $d = e($snippet['description']);

                return new HtmlString(
                    '<div class="space-y-2 text-sm"><p><span class="font-medium text-gray-600 dark:text-gray-400">Title:</span> '.$t.'</p>'
                    .'<p><span class="font-medium text-gray-600 dark:text-gray-400">Description:</span> '.$d.'</p></div>'
                );
            })
            ->columnSpanFull();
    }

    /**
     * Публикация и видимость (без тарифов — см. {@see self::pricingProfileFields()}).
     *
     * @return array<int, Component>
     */
    public static function publishingVisibilityFields(): array
    {
        return [
            Select::make('status')
                ->label('Статус')
                ->id('motorcycle-status')
                ->options(Motorcycle::statuses())
                ->required()
                ->default('available'),
            TextInput::make('sort_order')
                ->label('Порядок сортировки')
                ->id('motorcycle-sort-order')
                ->numeric()
                ->default(0),
            Toggle::make('show_on_home')
                ->label('Показывать на главной')
                ->id('motorcycle-show-on-home')
                ->default(false),
            Toggle::make('show_in_catalog')
                ->label('Показывать в каталоге')
                ->id('motorcycle-show-in-catalog')
                ->default(true),
            Toggle::make('is_recommended')
                ->label('Рекомендуемый')
                ->id('motorcycle-is-recommended')
                ->default(false),
            Select::make('category_id')
                ->label('Категория')
                ->id('motorcycle-category')
                ->relationship('category', 'name')
                ->searchable()
                ->preload(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function publishingFields(): array
    {
        return [
            ...self::publishingVisibilityFields(),
            ...self::pricingProfileFields(),
        ];
    }

    /**
     * Краткая строка для заголовка свёрнутой строки тарифа в Repeater.
     *
     * @param  array<string, mixed>  $state
     */
    public static function formatTariffRepeaterItemLabel(array $state, string $currencyIso = MotorcyclePricingSchema::DEFAULT_CURRENCY): string
    {
        $currencyIso = strtoupper(substr(trim($currencyIso), 0, 3));
        if ($currencyIso === '') {
            $currencyIso = MotorcyclePricingSchema::DEFAULT_CURRENCY;
        }

        $label = trim((string) ($state['label'] ?? ''));
        $head = $label !== '' ? $label : 'Тариф';

        $kind = TariffKind::tryFrom((string) ($state['kind'] ?? '')) ?? TariffKind::FixedPerDay;
        $kindShort = match ($kind) {
            TariffKind::FixedPerDay => 'Сутки',
            TariffKind::FixedPerRental => 'Период',
            TariffKind::FixedPerHourBlock => 'Блок ч.',
            TariffKind::OnRequest => 'По запросу',
            TariffKind::Informational => 'Инфо',
        };

        $amountPart = null;
        if (! in_array($kind, [TariffKind::OnRequest, TariffKind::Informational], true)) {
            $rawMajor = $state['amount_major'] ?? null;
            if ($rawMajor !== null && $rawMajor !== '') {
                $major = (int) $rawMajor;
                $sym = $currencyIso === 'RUB' ? '₽' : $currencyIso;

                $amountPart = number_format(max(0, $major), 0, '', ' ').' '.$sym;
            } else {
                $amountPart = '—';
            }
        }

        $mode = ApplicabilityMode::tryFrom((string) ($state['applicability_mode'] ?? '')) ?? ApplicabilityMode::Always;
        $appShort = match ($mode) {
            ApplicabilityMode::Always => 'Всегда',
            ApplicabilityMode::DurationRangeDays => 'Диапазон дн.',
            ApplicabilityMode::DurationMinDays => 'Мин. дн.',
            ApplicabilityMode::ManualOnly => 'Вручную',
        };

        $parts = [$head, $kindShort];
        if ($amountPart !== null) {
            $parts[] = $amountPart;
        }
        $parts[] = $appShort;

        return implode(' · ', $parts);
    }

    /**
     * Тарифы, отображение на карточке и финансовые условия (канонически в pricing_profile_json).
     *
     * @return array<int, Component>
     */
    public static function pricingProfileFields(): array
    {
        $kindOptions = collect(TariffKind::cases())->mapWithKeys(fn (TariffKind $k): array => [$k->value => match ($k) {
            TariffKind::FixedPerDay => 'Фикс за сутки / день',
            TariffKind::FixedPerRental => 'Фикс за период',
            TariffKind::FixedPerHourBlock => 'Блок часов',
            TariffKind::OnRequest => 'По запросу',
            TariffKind::Informational => 'Информационный',
        }])->all();

        $appOptions = collect(ApplicabilityMode::cases())->mapWithKeys(fn (ApplicabilityMode $m): array => [$m->value => match ($m) {
            ApplicabilityMode::Always => 'Всегда',
            ApplicabilityMode::DurationRangeDays => 'Диапазон дней',
            ApplicabilityMode::DurationMinDays => 'Минимум дней',
            ApplicabilityMode::ManualOnly => 'Только вручную',
        }])->all();

        return [
            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->gap()
                ->schema([
                    Grid::make(['default' => 1])
                        ->schema([
                            TextInput::make('pricing_currency')
                                ->label('Валюта (ISO)')
                                ->id('motorcycle-pricing-currency')
                                ->default(MotorcyclePricingSchema::DEFAULT_CURRENCY)
                                ->maxLength(3)
                                ->live()
                                ->helperText('Профиль ценообразования v'.MotorcyclePricingSchema::PROFILE_VERSION),
                            Repeater::make('pricing_tariffs')
                                ->label('Тарифы')
                                ->id('motorcycle-pricing-tariffs')
                                ->helperText('Перетащите тарифы в нужном порядке. Свернутые строки показывают краткое резюме; раскрывайте только ту, что редактируете. Если для периода подходят несколько тарифов одинаково, система выберет верхний в списке.')
                                ->minItems(1)
                                ->collapsed()
                                ->itemLabel(function (array $state, Get $get): string {
                                    $cur = (string) ($get('pricing_currency') ?? MotorcyclePricingSchema::DEFAULT_CURRENCY);

                                    return self::formatTariffRepeaterItemLabel($state, $cur);
                                })
                                ->schema([
                                    Hidden::make('id')
                                        ->default(fn () => (string) Str::uuid()),
                                    Grid::make(['default' => 1])
                                        ->gap()
                                        ->schema([
                                            Fieldset::make('Условия и цена')
                                                ->contained(false)
                                                ->columns(2)
                                                ->schema([
                                                    TextInput::make('label')
                                                        ->label('Название')
                                                        ->required()
                                                        ->maxLength(120)
                                                        ->placeholder('Например: Сутки, будни, почасовой тариф (как увидит гость)')
                                                        ->columnSpanFull(),
                                                    Select::make('kind')
                                                        ->label('Тип')
                                                        ->options($kindOptions)
                                                        ->required()
                                                        ->native(true)
                                                        ->live(),
                                                    MotorcyclePricingProfileFormHydrator::profileMoneyInput('amount_major', 'Сумма')
                                                        ->visible(fn (Get $get): bool => ! in_array((string) $get('kind'), [TariffKind::OnRequest->value, TariffKind::Informational->value], true)),
                                                    TextInput::make('block_hours')
                                                        ->label('Часов в блоке')
                                                        ->numeric()
                                                        ->default(24)
                                                        ->visible(fn (Get $get): bool => (string) $get('kind') === TariffKind::FixedPerHourBlock->value),
                                                    Textarea::make('note')
                                                        ->label('Подсказка для «По запросу»')
                                                        ->rows(2)
                                                        ->columnSpanFull()
                                                        ->visible(fn (Get $get): bool => (string) $get('kind') === TariffKind::OnRequest->value),
                                                    Select::make('applicability_mode')
                                                        ->label('Применимость')
                                                        ->options($appOptions)
                                                        ->required()
                                                        ->native(true)
                                                        ->live()
                                                        ->columnSpanFull(),
                                                    TextInput::make('min_days')
                                                        ->label('Мин. дней')
                                                        ->numeric()
                                                        ->default(1)
                                                        ->visible(fn (Get $get): bool => in_array((string) $get('applicability_mode'), [
                                                            ApplicabilityMode::DurationRangeDays->value,
                                                            ApplicabilityMode::DurationMinDays->value,
                                                        ], true)),
                                                    TextInput::make('max_days')
                                                        ->label('Макс. дней')
                                                        ->numeric()
                                                        ->default(3)
                                                        ->visible(fn (Get $get): bool => (string) $get('applicability_mode') === ApplicabilityMode::DurationRangeDays->value),
                                                ]),
                                            Fieldset::make('На сайте')
                                                ->contained(false)
                                                ->schema([
                                                    Select::make('catalog_day_unit')
                                                        ->label('Как писать на сайте (сутки / день)')
                                                        ->options([
                                                            TariffCatalogDayUnit::FullDay->value => 'Сутки — «за сутки», диапазоны «… суток»',
                                                            TariffCatalogDayUnit::ShortDay->value => 'День — «за день», диапазоны «… дня»',
                                                        ])
                                                        ->default(TariffCatalogDayUnit::FullDay->value)
                                                        ->native(true)
                                                        ->visible(fn (Get $get): bool => (string) $get('kind') === TariffKind::FixedPerDay->value)
                                                        ->helperText('Только текст для посетителей. Расчёт в калькуляторе по-прежнему за выбранные календарные дни.'),
                                                    TextInput::make('catalog_public_hint')
                                                        ->label('Пояснение в скобках на сайте')
                                                        ->maxLength(80)
                                                        ->visible(fn (Get $get): bool => in_array((string) $get('kind'), [
                                                            TariffKind::FixedPerDay->value,
                                                            TariffKind::FixedPerRental->value,
                                                            TariffKind::FixedPerHourBlock->value,
                                                            TariffKind::Informational->value,
                                                        ], true))
                                                        ->helperText('Например: 10 часов — получится «Название (10 часов)». Скобки добавляются сами, без «( )» в поле.'),
                                                    Grid::make(['default' => 1])
                                                        ->columnSpanFull()
                                                        ->schema([
                                                            Toggle::make('show_on_card')
                                                                ->label('Показывать в каталоге')
                                                                ->hintIcon('heroicon-o-information-circle')
                                                                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                                                    'Публичная плитка моделей: каталог и блоки на лендинге, где гость видит карточки целиком.',
                                                                    'Логика как у одной радиокнопки в группе: при включении здесь остальные строки тарифов сбрасываются автоматически (визуально по-прежнему переключатель).',
                                                                    'Крупная сумма «от …» и основная подпись под ней задаются полем «Основной тариф на карточке» ниже — не этим переключателем.',
                                                                    'Системе нужна хотя бы одна строка с включённой видимостью где‑либо (каталог, страница модели или калькулятор).',
                                                                ))
                                                                ->default(false)
                                                                ->live()
                                                                ->afterStateUpdated(function (?bool $state, Set $set, Get $get, Toggle $toggle): void {
                                                                    self::applyExclusiveShowOnCardInRepeater($state, $set, $get, $toggle);
                                                                })
                                                                ->inline(false),
                                                            Toggle::make('show_on_detail')
                                                                ->label('Показывать на странице модели')
                                                                ->hintIcon('heroicon-o-information-circle')
                                                                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                                                    'Список условий и цен на странице конкретной модели (полная карточка мотоцикла на сайте).',
                                                                    'Формулировки и «сутки / день» совпадают с тем, что видит гость в блоке цен.',
                                                                    'Выключите, если строка нужна только в калькуляторе или не должна светиться в тексте на странице модели.',
                                                                ))
                                                                ->default(true)
                                                                ->inline(false),
                                                            Toggle::make('show_in_quote')
                                                                ->label('Показывать в калькуляторе бронирования')
                                                                ->hintIcon('heroicon-o-information-circle')
                                                                ->hintIconTooltip(fn () => HintIconTooltip::lines(
                                                                    'Участие тарифа в автоматическом расчёте при выборе дат бронирования на сайте.',
                                                                    'Выключённая строка не попадает в кандидаты для калькулятора.',
                                                                    'На плитке в каталоге и в списке на странице модели строка может оставаться — если включены соответствующие переключатели выше.',
                                                                ))
                                                                ->default(true)
                                                                ->inline(false),
                                                        ]),
                                                ]),
                                        ]),
                                ])
                                ->defaultItems(1)
                                ->addActionLabel('Добавить тариф')
                                ->reorderable()
                                ->collapsible()
                                ->columnSpanFull(),
                        ]),
                    Grid::make(['default' => 1])
                        ->schema([
                            Section::make('Финансовые условия')
                                ->description('Залог, предоплата и подпись в каталоге — отдельно от списка тарифов.')
                                ->schema([
                                    MotorcyclePricingProfileFormHydrator::profileMoneyInput('pricing_deposit_amount', 'Залог'),
                                    MotorcyclePricingProfileFormHydrator::profileMoneyInput('pricing_prepayment_amount', 'Предоплата'),
                                    TextInput::make('pricing_catalog_price_note')
                                        ->label('Подпись под ценой в каталоге')
                                        ->maxLength(80)
                                        ->placeholder('Только реальное условие')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->compact(),
                            Section::make('Отображение в каталоге')
                                ->description('Какой тариф даёт крупную цену «от …» на сайте: в плитке каталога и в шапке страницы модели. Сама сумма — в выбранной строке тарифа выше, поле «Сумма». Вторичная подпись — ниже.')
                                ->schema([
                                    Select::make('pricing_card_primary_tariff_id')
                                        ->label('Основной тариф на карточке')
                                        ->helperText('Должен совпадать с одной из строк в «Тарифы». Для типа «за сутки» на сайте показывается «от {Сумма} за сутки» (если профиль валиден).')
                                        ->options(function (Get $get): array {
                                            $opts = [];
                                            foreach ($get('pricing_tariffs') ?? [] as $row) {
                                                if (! is_array($row)) {
                                                    continue;
                                                }
                                                $id = (string) ($row['id'] ?? '');
                                                if ($id === '') {
                                                    continue;
                                                }
                                                $opts[$id] = (string) ($row['label'] ?? $id);
                                            }

                                            return $opts;
                                        })
                                        ->native(true),
                                    Select::make('pricing_card_secondary_mode')
                                        ->label('Вторичная подсказка на карточке')
                                        ->options([
                                            'none' => 'Нет',
                                            'hint_text' => 'Текст',
                                            'secondary_tariff' => 'Другой тариф',
                                        ])
                                        ->default('none')
                                        ->native(true)
                                        ->live(),
                                    TextInput::make('pricing_card_secondary_text')
                                        ->label('Текст подсказки')
                                        ->maxLength(120)
                                        ->visible(fn (Get $get): bool => (string) $get('pricing_card_secondary_mode') === 'hint_text'),
                                    Select::make('pricing_card_secondary_tariff_id')
                                        ->label('Тариф для подсказки')
                                        ->options(function (Get $get): array {
                                            $opts = [];
                                            foreach ($get('pricing_tariffs') ?? [] as $row) {
                                                if (! is_array($row)) {
                                                    continue;
                                                }
                                                $id = (string) ($row['id'] ?? '');
                                                if ($id === '') {
                                                    continue;
                                                }
                                                $opts[$id] = (string) ($row['label'] ?? $id);
                                            }

                                            return $opts;
                                        })
                                        ->visible(fn (Get $get): bool => (string) $get('pricing_card_secondary_mode') === 'secondary_tariff')
                                        ->native(true),
                                    TextInput::make('pricing_detail_tariffs_limit')
                                        ->label('Лимит строк на странице модели')
                                        ->numeric()
                                        ->nullable(),
                                ])
                                ->columns(2)
                                ->compact(),
                        ]),
                ]),
        ];
    }

    /**
     * Инварианты «флот выключен ⇒ не per_unit», «не selected ⇒ без привязки локаций карточки».
     * Дублирует UI на backend (create / прямые вызовы API).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFleetLocationFormState(array $data): array
    {
        $data['uses_fleet_units'] = (bool) ($data['uses_fleet_units'] ?? false);
        $mode = (string) ($data['location_mode'] ?? MotorcycleLocationMode::Everywhere->value);
        if (! $data['uses_fleet_units'] && $mode === MotorcycleLocationMode::PerUnit->value) {
            $data['location_mode'] = MotorcycleLocationMode::Everywhere->value;
            $mode = MotorcycleLocationMode::Everywhere->value;
        }
        if ($mode !== MotorcycleLocationMode::Selected->value) {
            $data['tenant_location_ids'] = [];
        }

        return $data;
    }

    /**
     * Синхронизация полей формы create/edit ресурса с pricing_profile_json (до сохранения модели).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergePricingProfileIntoMotorcycleData(array $data): array
    {
        if (! isset($data['pricing_tariffs'])) {
            return $data;
        }

        if (isset($data['pricing_card_secondary_mode'])) {
            $data['pricing_card_secondary_mode'] = MotorcyclePricingProfileFormHydrator::normalizeCardSecondaryMode(
                (string) $data['pricing_card_secondary_mode'],
            );
        }

        $tariffRows = is_array($data['pricing_tariffs']) ? array_values($data['pricing_tariffs']) : [];
        $tariffIds = [];
        foreach ($tariffRows as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') !== '') {
                $tariffIds[(string) $row['id']] = true;
            }
        }

        if ($tariffRows !== [] && count($tariffRows) === 1) {
            $only = $tariffRows[0];
            if (is_array($only)
                && ($data['pricing_card_primary_tariff_id'] ?? '') === ''
                && (string) ($only['id'] ?? '') !== '') {
                $data['pricing_card_primary_tariff_id'] = (string) $only['id'];
            }
        }

        if ((string) ($data['pricing_card_secondary_mode'] ?? 'none') === 'secondary_tariff') {
            $sec = (string) ($data['pricing_card_secondary_tariff_id'] ?? '');
            if ($sec !== '' && ! isset($tariffIds[$sec])) {
                $data['pricing_card_secondary_mode'] = 'none';
                $data['pricing_card_secondary_tariff_id'] = '';
            }
        }

        $flat = [
            'currency' => (string) ($data['pricing_currency'] ?? MotorcyclePricingSchema::DEFAULT_CURRENCY),
            'tariffs' => $tariffRows,
            'card_primary_tariff_id' => (string) ($data['pricing_card_primary_tariff_id'] ?? ''),
            'card_secondary_mode' => (string) ($data['pricing_card_secondary_mode'] ?? 'none'),
            'card_secondary_text' => (string) ($data['pricing_card_secondary_text'] ?? ''),
            'card_secondary_tariff_id' => (string) ($data['pricing_card_secondary_tariff_id'] ?? ''),
            'detail_tariffs_limit' => $data['pricing_detail_tariffs_limit'] ?? null,
            'deposit_amount' => $data['pricing_deposit_amount'] ?? null,
            'prepayment_amount' => $data['pricing_prepayment_amount'] ?? null,
            'catalog_price_note' => (string) ($data['pricing_catalog_price_note'] ?? ''),
        ];

        $data['pricing_profile_json'] = MotorcyclePricingProfileFormHydrator::flatFormToProfile($flat);
        $blocking = app(MotorcyclePricingProfileValidator::class)->blockingErrorsForSave($data['pricing_profile_json']);
        if ($blocking !== []) {
            throw ValidationException::withMessages([
                'pricing_tariffs' => implode(' ', $blocking),
            ]);
        }
        $data['pricing_profile_schema_version'] = MotorcyclePricingSchema::PROFILE_VERSION;

        foreach ([
            'pricing_currency', 'pricing_tariffs', 'pricing_card_primary_tariff_id',
            'pricing_card_secondary_mode', 'pricing_card_secondary_text', 'pricing_card_secondary_tariff_id',
            'pricing_detail_tariffs_limit', 'pricing_deposit_amount', 'pricing_prepayment_amount', 'pricing_catalog_price_note',
        ] as $k) {
            unset($data[$k]);
        }

        return $data;
    }

    /**
     * Поля формы ресурса из pricing_profile_json модели.
     *
     * @return array<string, mixed>
     */
    public static function extractPricingProfileFormDefaults(Motorcycle $motorcycle): array
    {
        $raw = app(MotorcyclePricingProfileLoader::class)->loadOrSynthesize($motorcycle);

        $flat = MotorcyclePricingProfileFormHydrator::profileToFlatForm($raw);

        return [
            'pricing_currency' => $flat['currency'],
            'pricing_tariffs' => $flat['tariffs'],
            'pricing_card_primary_tariff_id' => $flat['card_primary_tariff_id'],
            'pricing_card_secondary_mode' => $flat['card_secondary_mode'],
            'pricing_card_secondary_text' => $flat['card_secondary_text'],
            'pricing_card_secondary_tariff_id' => $flat['card_secondary_tariff_id'],
            'pricing_detail_tariffs_limit' => $flat['detail_tariffs_limit'],
            'pricing_deposit_amount' => $flat['deposit_amount'],
            'pricing_prepayment_amount' => $flat['prepayment_amount'],
            'pricing_catalog_price_note' => $flat['catalog_price_note'],
        ];
    }

    /**
     * Режим учёта (единицы парка) и доступность по локациям — для create и для Livewire-блока на edit.
     *
     * @return array<int, Component>
     */
    public static function fleetAndLocationCardFields(): array
    {
        return [
            Toggle::make('uses_fleet_units')
                ->label('Использовать единицы парка')
                ->helperText('Несколько физических экземпляров одной карточки. Строки единиц добавляются после сохранения карточки, на экране редактирования.')
                ->default(false)
                ->live()
                ->afterStateUpdated(function (Set $set, ?bool $state, Get $get): void {
                    if ($state) {
                        return;
                    }
                    $mode = (string) ($get('location_mode') ?? '');
                    if ($mode === MotorcycleLocationMode::PerUnit->value) {
                        $set('location_mode', MotorcycleLocationMode::Everywhere->value);
                        $mode = MotorcycleLocationMode::Everywhere->value;
                    }
                    if ($mode !== MotorcycleLocationMode::Selected->value) {
                        $set('tenant_location_ids', []);
                    }
                }),
            Select::make('location_mode')
                ->label('Где доступен товар')
                ->options(function (Get $get): array {
                    $base = [
                        MotorcycleLocationMode::Everywhere->value => MotorcycleLocationMode::Everywhere->label(),
                        MotorcycleLocationMode::Selected->value => 'Только в выбранных локациях',
                    ];
                    if ($get('uses_fleet_units')) {
                        $base[MotorcycleLocationMode::PerUnit->value] = MotorcycleLocationMode::PerUnit->label();
                    }

                    return $base;
                })
                ->default(MotorcycleLocationMode::Everywhere->value)
                ->required()
                ->native(true)
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state, Get $get): void {
                    if ($state === MotorcycleLocationMode::PerUnit->value && ! $get('uses_fleet_units')) {
                        $set('location_mode', MotorcycleLocationMode::Everywhere->value);
                    }
                    if ($state !== MotorcycleLocationMode::Selected->value) {
                        $set('tenant_location_ids', []);
                    }
                }),
            CheckboxList::make('tenant_location_ids')
                ->label('Локации')
                ->options(function (): array {
                    return TenantLocation::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->visible(fn (Get $get): bool => $get('location_mode') === MotorcycleLocationMode::Selected->value)
                ->columns(2)
                ->required(fn (Get $get): bool => $get('location_mode') === MotorcycleLocationMode::Selected->value)
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if ($get('location_mode') !== MotorcycleLocationMode::Selected->value) {
                            return;
                        }
                        $ids = is_array($value) ? array_values(array_filter($value, fn ($v) => $v !== null && $v !== '')) : [];
                        if ($ids === []) {
                            $fail('Выберите хотя бы одну локацию.');
                        }
                    },
                ])
                ->dehydrated(fn (Get $get): bool => $get('location_mode') === MotorcycleLocationMode::Selected->value)
                ->helperText('При режиме «только в выбранных» нужно выбрать минимум одну локацию из справочника «Локации».'),
        ];
    }

    /**
     * @return array<int, TenantSpatieMediaLibraryFileUpload>
     */
    public static function mediaUploadFields(): array
    {
        return [
            TenantSpatieMediaLibraryFileUpload::make('cover')
                ->collection('cover')
                ->disk(config('media-library.disk_name'))
                ->visibility('public')
                ->conversionsDisk(config('media-library.disk_name'))
                ->image()
                ->imagePreviewHeight('320px')
                ->extraFieldWrapperAttributes(['class' => 'fi-moto-media-cover-field'])
                ->label('Обложка')
                ->helperText('Основное изображение карточки. Рекомендуется 16:9. При редактировании файл сохраняется в медиатеку сразу после успешной загрузки. При создании новой карточки — после первого сохранения формы.')
                ->id('motorcycle-cover')
                ->columnSpanFull()
                ->fetchFileInformation(false)
                ->orientImagesFromExif(true)
                ->maxSize(15360)
                ->afterStateUpdated(MotorcycleMediaPersistence::persistAfterUploadStateChange(...)),
            TenantSpatieMediaLibraryFileUpload::make('gallery')
                ->collection('gallery')
                ->disk(config('media-library.disk_name'))
                ->visibility('public')
                ->conversionsDisk(config('media-library.disk_name'))
                ->image()
                ->imagePreviewHeight('240px')
                ->extraFieldWrapperAttributes(['class' => 'fi-moto-media-gallery-field'])
                ->multiple()
                ->maxFiles(10)
                ->reorderable()
                ->label('Галерея')
                ->helperText('Дополнительные изображения для слайдера. На экране редактирования новые файлы сохраняются сразу после загрузки.')
                ->id('motorcycle-gallery')
                ->columnSpanFull()
                ->fetchFileInformation(false)
                ->orientImagesFromExif(true)
                ->maxSize(15360)
                ->afterStateUpdated(MotorcycleMediaPersistence::persistAfterUploadStateChange(...)),
        ];
    }

    public static function seoMetaSection(): Section
    {
        return SeoMetaFields::make(useTabs: true);
    }

    /**
     * Разбор абсолютного state path поля show_on_card (последний сегмент {@code .pricing_tariffs.} — устойчиво к префиксу формы).
     *
     * @return array{repeaterBase: string, currentItemKey: string, inItemSuffix: string}|null
     */
    public static function parseExclusiveShowOnCardBranch(string $fullPath): ?array
    {
        $suffix = '.show_on_card';
        if ($fullPath === '' || ! str_ends_with($fullPath, $suffix)) {
            return null;
        }
        $branch = substr($fullPath, 0, -strlen($suffix));
        $needle = '.pricing_tariffs.';
        $pos = strrpos($branch, $needle);
        if ($pos !== false) {
            $repeaterBase = substr($branch, 0, $pos).'.pricing_tariffs';
            $afterRepeater = substr($branch, $pos + strlen($needle));
        } elseif (str_starts_with($branch, 'pricing_tariffs.')) {
            $repeaterBase = 'pricing_tariffs';
            $afterRepeater = substr($branch, strlen('pricing_tariffs.'));
        } else {
            return null;
        }
        $dotPos = strpos($afterRepeater, '.');
        $currentItemKey = $dotPos === false ? $afterRepeater : substr($afterRepeater, 0, $dotPos);
        $inItemSuffix = $dotPos === false ? '' : substr($afterRepeater, $dotPos + 1);

        return [
            'repeaterBase' => $repeaterBase,
            'currentItemKey' => $currentItemKey,
            'inItemSuffix' => $inItemSuffix,
        ];
    }

    private static function applyExclusiveShowOnCardInRepeater(?bool $state, Set $set, Get $get, Toggle $toggle): void
    {
        if ($state !== true) {
            return;
        }
        $parsed = self::parseExclusiveShowOnCardBranch((string) ($toggle->getStatePath() ?? ''));
        if ($parsed === null) {
            return;
        }
        $repeaterBase = $parsed['repeaterBase'];
        $currentItemKey = $parsed['currentItemKey'];
        $inItemSuffix = $parsed['inItemSuffix'];
        $suffix = '.show_on_card';

        $tariffs = $get('/'.$repeaterBase, true);
        if (! is_array($tariffs)) {
            return;
        }
        foreach (array_keys($tariffs) as $key) {
            if ((string) $key === $currentItemKey) {
                continue;
            }
            $targetBranch = $repeaterBase.'.'.$key.($inItemSuffix !== '' ? '.'.$inItemSuffix : '');
            $set('/'.$targetBranch.$suffix, false, true);
        }
    }
}
