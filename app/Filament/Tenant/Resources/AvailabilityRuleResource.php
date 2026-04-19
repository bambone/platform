<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Tenant\Resources\AvailabilityRuleResource\Pages;
use App\Filament\Tenant\Support\SchedulingAdminNavigationPrerequisites;
use App\Models\AvailabilityRule;
use App\Models\SchedulingResource;
use App\Scheduling\Enums\AvailabilityRuleType;
use App\Scheduling\Enums\SchedulingScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class AvailabilityRuleResource extends Resource
{
    protected static ?string $model = AvailabilityRule::class;

    protected static ?string $navigationLabel = 'Правила доступности';

    protected static ?string $modelLabel = 'Правило доступности';

    protected static ?string $pluralModelLabel = 'Правила доступности';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingAvailability';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! static::$shouldRegisterNavigation) {
            return false;
        }

        return SchedulingAdminNavigationPrerequisites::tenantHasSchedulingResources(currentTenant());
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = currentTenant()?->id;

        return parent::getEloquentQuery()
            ->whereHas('schedulingResource', function (Builder $q) use ($tenantId): void {
                $q->where('scheduling_scope', SchedulingScope::Tenant)
                    ->where('tenant_id', $tenantId);
            });
    }

    public static function form(Schema $schema): Schema
    {
        $typeOptions = [];
        foreach (AvailabilityRuleType::cases() as $c) {
            $typeOptions[$c->value] = $c->label();
        }

        return $schema
            ->components([
                Section::make('Правило доступности')
                    ->description(FilamentInlineMarkdown::toHtml(
                        'Правила задают **еженедельное расписание** выбранного **календарного ресурса** (сотрудник, зал и т.д.). **Окно приёма** расширяет доступные часы; **перерыв** сужает их. Время всегда в **часовом поясе ресурса** — он указан в карточке ресурса.'
                    ))
                    ->schema([
                        Select::make('scheduling_resource_id')
                            ->label('Календарный ресурс')
                            ->relationship(
                                'schedulingResource',
                                'label',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('scheduling_scope', SchedulingScope::Tenant)
                                    ->where('tenant_id', currentTenant()?->id)
                                    ->orderBy('label'),
                            )
                            ->required()
                            ->native()
                            ->live()
                            ->helperText(function (Get $get): string|Htmlable|null {
                                $id = $get('scheduling_resource_id');
                                if ($id === null || $id === '') {
                                    return 'Чей график настраиваете: сотрудник, ресурс и т.д.';
                                }
                                $res = SchedulingResource::query()->find($id);
                                if ($res === null) {
                                    return null;
                                }
                                $tz = e($res->timezone);

                                return FilamentInlineMarkdown::toHtml(
                                    "Часовой пояс для расчёта слотов: `{$tz}`. Время «С» и «До» ниже — в этом поясе."
                                );
                            }),
                        Select::make('rule_type')
                            ->label('Тип правила')
                            ->options($typeOptions)
                            ->required()
                            ->native()
                            ->live()
                            ->helperText(function (Get $get): string {
                                $raw = $get('rule_type');
                                $type = is_string($raw) ? AvailabilityRuleType::tryFrom($raw) : null;

                                return $type?->formHelperText()
                                    ?? 'Выберите тип: сначала обычно добавляют окна приёма, при необходимости — перерывы.';
                            }),
                        Select::make('weekday')
                            ->label('День недели')
                            ->options(self::isoWeekdayOptions())
                            ->required()
                            ->native()
                            ->helperText('Понедельник = 1 … воскресенье = 7 (как в календаре ISO).'),
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextInput::make('starts_at_local')
                                    ->label('С')
                                    ->required()
                                    ->extraInputAttributes(['type' => 'time', 'step' => 300])
                                    ->helperText('Начало интервала в поясе ресурса.'),
                                TextInput::make('ends_at_local')
                                    ->label('До')
                                    ->required()
                                    ->extraInputAttributes(['type' => 'time', 'step' => 300])
                                    ->helperText('Конец интервала. Должен быть позже «С» (в пределах одного дня).'),
                            ]),
                        Section::make('Ограничить по датам')
                            ->description('По умолчанию правило повторяется каждую выбранную неделю без срока. Заполните поля, если нужен сезон или разовый период.')
                            ->collapsed()
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        DatePicker::make('valid_from')
                                            ->label('Действует не раньше')
                                            ->native(true),
                                        DatePicker::make('valid_to')
                                            ->label('Действует не позже')
                                            ->native(true),
                                    ]),
                            ]),
                        Toggle::make('is_active')
                            ->label('Правило включено')
                            ->helperText('Выключенные правила не участвуют в расчёте слотов.')
                            ->default(true),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function isoWeekdayOptions(): array
    {
        return [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
    }

    private static function formatTimeForTable(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }
        $s = (string) $state;

        return strlen($s) >= 5 ? substr($s, 0, 5) : $s;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schedulingResource.label')->label('Ресурс'),
                TextColumn::make('rule_type')
                    ->label('Тип')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof AvailabilityRuleType) {
                            return $state->label();
                        }

                        return AvailabilityRuleType::tryFrom((string) $state)?->label() ?? (string) $state;
                    }),
                TextColumn::make('weekday')
                    ->label('День')
                    ->formatStateUsing(fn ($state): string => self::isoWeekdayOptions()[(int) $state] ?? (string) $state),
                TextColumn::make('starts_at_local')
                    ->label('С')
                    ->formatStateUsing(fn ($state): string => self::formatTimeForTable($state))
                    ->toggleable(),
                TextColumn::make('ends_at_local')
                    ->label('До')
                    ->formatStateUsing(fn ($state): string => self::formatTimeForTable($state))
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Вкл.')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'Да' : 'Нет')
                    ->color(fn ($state): string => filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'success' : 'gray'),
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
            'index' => Pages\ListAvailabilityRules::route('/'),
            'create' => Pages\CreateAvailabilityRule::route('/create'),
            'edit' => Pages\EditAvailabilityRule::route('/{record}/edit'),
        ];
    }
}
