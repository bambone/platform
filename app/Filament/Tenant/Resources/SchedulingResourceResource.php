<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Shared\TimezoneSelect;
use App\Filament\Tenant\Resources\SchedulingResourceResource\Pages;
use App\Models\SchedulingResource;
use App\Models\SchedulingResourceTypeLabel;
use App\Scheduling\Enums\SchedulingResourceType;
use App\Scheduling\Enums\SchedulingScope;
use App\Scheduling\Enums\TentativeEventsPolicy;
use App\Scheduling\Enums\UnconfirmedRequestsPolicy;
use App\Scheduling\SchedulingTimezoneOptions;
use App\Tenant\Filament\TenantCabinetUserPicker;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SchedulingResourceResource extends Resource
{
    protected static ?string $model = SchedulingResource::class;

    protected static ?string $navigationLabel = 'Ресурсы расписания';

    protected static ?string $modelLabel = 'Ресурс';

    protected static ?string $pluralModelLabel = 'Ресурсы';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingCore';

    protected static ?int $navigationSort = 13;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
    }

    public static function getEloquentQuery(): Builder
    {
        $table = (new SchedulingResource)->getTable();

        return parent::getEloquentQuery()
            ->where($table.'.scheduling_scope', SchedulingScope::Tenant)
            ->where($table.'.tenant_id', currentTenant()?->id);
    }

    /**
     * @return array<string, string> value => подпись
     */
    public static function builtInResourceTypeLabelMap(): array
    {
        return [
            SchedulingResourceType::Person->value => 'Человек (сотрудник)',
            SchedulingResourceType::Team->value => 'Команда',
            SchedulingResourceType::Room->value => 'Помещение / зал',
            SchedulingResourceType::Vehicle->value => 'Техника / транспорт',
            SchedulingResourceType::AssetPool->value => 'Пул объектов',
            SchedulingResourceType::Branch->value => 'Филиал',
            SchedulingResourceType::Generic->value => 'Общий тип',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function resourceTypeOptionsFor(?int $tenantId): array
    {
        $builtIns = self::builtInResourceTypeLabelMap();
        if ($tenantId === null) {
            return $builtIns;
        }

        $custom = SchedulingResourceTypeLabel::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('label')
            ->pluck('label', 'slug')
            ->all();

        return $builtIns + $custom;
    }

    public static function formatResourceTypeLabelForTable(?string $state, ?int $tenantId, ?SchedulingResource $record = null): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        $builtIns = self::builtInResourceTypeLabelMap();
        if (isset($builtIns[$state])) {
            return $builtIns[$state];
        }

        if ($record !== null) {
            $attrs = $record->getAttributes();
            if (array_key_exists('resource_type_custom_label', $attrs) && $attrs['resource_type_custom_label'] !== null && $attrs['resource_type_custom_label'] !== '') {
                return (string) $attrs['resource_type_custom_label'];
            }
        }

        if ($tenantId !== null) {
            $custom = SchedulingResourceTypeLabel::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $state)
                ->value('label');
            if ($custom !== null) {
                return $custom;
            }
        }

        return $state;
    }

    public static function form(Schema $schema): Schema
    {
        $tentativeOptions = [
            TentativeEventsPolicy::TreatAsBusy->value => 'Считать занятым (слот недоступен)',
            TentativeEventsPolicy::TreatAsFree->value => 'Считать свободным (клиент может записаться)',
            TentativeEventsPolicy::ProviderDefault->value => 'Как задано у календаря (Google, CalDAV и т.д.)',
        ];

        $unconfirmedOptions = [
            UnconfirmedRequestsPolicy::Ignore->value => 'Не учитывать заявки — слоты не блокируются из‑за ожидающих записей',
            UnconfirmedRequestsPolicy::HoldOnly->value => 'Блокировать только кратковременную бронь (пока клиент оформляет запись)',
            UnconfirmedRequestsPolicy::PendingIsBusy->value => 'Блокировать бронь и заявки «ожидают подтверждения»',
            UnconfirmedRequestsPolicy::PendingAndConfirmedAreBusy->value => 'Блокировать бронь, ожидание и уже подтверждённые визиты',
            UnconfirmedRequestsPolicy::ConfirmedOnly->value => 'Блокировать только подтверждённые визиты (ожидающие не закрывают слот)',
        ];

        return $schema
            ->components([
                Section::make('Основное')
                    ->description('Кто или что даёт слоты в расписании.')
                    ->schema([
                        TextInput::make('label')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Как этот ресурс отображается в кабинете и на публичной записи.'),
                        Select::make('resource_type')
                            ->label('Тип ресурса')
                            ->required()
                            ->searchable()
                            ->options(fn () => self::resourceTypeOptionsFor(currentTenant()?->id))
                            ->getOptionLabelUsing(fn ($value): ?string => self::formatResourceTypeLabelForTable(
                                is_string($value) ? $value : null,
                                currentTenant()?->id,
                            ))
                            ->createOptionModalHeading('Добавить свой тип ресурса')
                            ->createOptionForm([
                                TextInput::make('label')
                                    ->label('Название типа')
                                    ->required()
                                    ->maxLength(120)
                                    ->helperText('Например: «Лодка», «Кабина», «Сервисная зона». Так тип будет виден в списке.'),
                                TextInput::make('slug')
                                    ->label('Код латиницей (необязательно)')
                                    ->maxLength(32)
                                    ->helperText('Например: boat или vip_cabin. Если пусто — код сформируется автоматически.'),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                $tenantId = currentTenant()?->id;
                                if ($tenantId === null) {
                                    throw ValidationException::withMessages([
                                        'label' => 'Не найден контекст клиента. Обновите страницу и попробуйте снова.',
                                    ]);
                                }

                                $label = trim((string) ($data['label'] ?? ''));
                                if ($label === '') {
                                    throw ValidationException::withMessages([
                                        'label' => 'Введите название типа.',
                                    ]);
                                }

                                $slugInput = trim((string) ($data['slug'] ?? ''));
                                $reserved = collect(SchedulingResourceType::cases())->map->value->all();

                                if ($slugInput !== '') {
                                    $slug = strtolower((string) preg_replace(
                                        '/[^a-z0-9_]+/',
                                        '',
                                        str_replace('-', '_', $slugInput),
                                    ));
                                } else {
                                    $slug = Str::slug($label, '_');
                                    $slug = strtolower((string) preg_replace('/[^a-z0-9_]+/', '_', $slug));
                                }

                                if ($slug === '') {
                                    $slug = 'type';
                                }

                                $slug = substr($slug, 0, 32);
                                $base = $slug;
                                $n = 0;

                                while (
                                    in_array($slug, $reserved, true)
                                    || SchedulingResourceTypeLabel::query()
                                        ->where('tenant_id', $tenantId)
                                        ->where('slug', $slug)
                                        ->exists()
                                ) {
                                    $n++;
                                    $suffix = '_'.$n;
                                    $slug = substr($base, 0, max(1, 32 - strlen($suffix))).$suffix;
                                    if ($n > 500) {
                                        throw ValidationException::withMessages([
                                            'slug' => 'Не удалось подобрать уникальный код. Укажите другой код вручную.',
                                        ]);
                                    }
                                }

                                SchedulingResourceTypeLabel::query()->create([
                                    'tenant_id' => $tenantId,
                                    'slug' => $slug,
                                    'label' => $label,
                                ]);

                                return $slug;
                            })
                            ->helperText('Стандартные типы можно дополнить своими: нажмите «+» у поля (создать вариант).'),
                        Select::make('user_id')
                            ->label('Пользователь (необязательно)')
                            ->relationship(
                                'user',
                                'name',
                                modifyQueryUsing: function (Builder $query): void {
                                    TenantCabinetUserPicker::applyCabinetTeamScope(
                                        $query,
                                        currentTenant()?->id,
                                    );
                                },
                            )
                            ->preload()
                            ->placeholder('—')
                            ->helperText('Если ресурс — конкретный сотрудник, можно привязать учётную запись из команды клиента.'),
                    ]),
                Section::make('Часовой пояс')
                    ->description('Используется для правил доступности и отображения времени для этого ресурса.')
                    ->schema([
                        TimezoneSelect::make('timezone')
                            ->required()
                            ->default(SchedulingTimezoneOptions::DEFAULT_IDENTIFIER),
                    ]),
                Section::make('Занятость и заявки')
                    ->description('Настройки ниже влияют на то, как внешний календарь и внутренние заявки закрывают слоты.')
                    ->schema([
                        Select::make('tentative_events_policy')
                            ->label('События «предварительно» во внешнем календаре')
                            ->options($tentativeOptions)
                            ->required()
                            ->searchable()
                            ->helperText('В Google и других календарях встреча может быть «tentative» (не окончательно). Решите, резервирует ли такое время слот для клиентов.'),
                        Select::make('unconfirmed_requests_policy')
                            ->label('Внутренние заявки на запись (ещё не подтверждены)')
                            ->options($unconfirmedOptions)
                            ->required()
                            ->searchable()
                            ->helperText('Когда клиент выбирает время, создаётся заявка. Эта опция задаёт, будет ли такая заявка закрывать слот для остальных до подтверждения.'),
                    ]),
                Section::make('Статус')
                    ->description('Неактивный ресурс не участвует в выдаче слотов клиентам.')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Ресурс активен')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->leftJoin('scheduling_resource_type_labels as srtl', function ($join): void {
                        $join->on('srtl.tenant_id', '=', 'scheduling_resources.tenant_id')
                            ->on('srtl.slug', '=', 'scheduling_resources.resource_type');
                    })
                    ->select('scheduling_resources.*')
                    ->addSelect(DB::raw('srtl.label as resource_type_custom_label'));
            })
            ->columns([
                TextColumn::make('label')
                    ->label('Название')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $base = (new SchedulingResource)->getTable();
                        $term = '%'.$search.'%';
                        $query->where(function (Builder $q) use ($base, $term): void {
                            $q->where($base.'.label', 'like', $term)
                                ->orWhere('srtl.label', 'like', $term);
                        });
                    }),
                TextColumn::make('resource_type')
                    ->label('Тип')
                    ->formatStateUsing(function (?string $state, SchedulingResource $record): string {
                        return self::formatResourceTypeLabelForTable(
                            $state,
                            currentTenant()?->id,
                            $record,
                        );
                    }),
                TextColumn::make('timezone')->label('Часовой пояс'),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    AdminFilamentDelete::makeBulkDeleteAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedulingResources::route('/'),
            'create' => Pages\CreateSchedulingResource::route('/create'),
            'edit' => Pages\EditSchedulingResource::route('/{record}/edit'),
        ];
    }
}
