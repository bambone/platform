<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Support\AdminEmptyState;
use App\Filament\Tenant\Resources\CalendarOccupancyMappingResource\Pages;
use App\Filament\Tenant\Support\SchedulingAdminNavigationPrerequisites;
use App\Models\CalendarOccupancyMapping;
use App\Models\CalendarSubscription;
use App\Models\SchedulingResource;
use App\Models\SchedulingTarget;
use App\Scheduling\Enums\MatchConfidence;
use App\Scheduling\Enums\MatchMode;
use App\Scheduling\Enums\OccupancyMappingType;
use App\Scheduling\Enums\SchedulingScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CalendarOccupancyMappingResource extends Resource
{
    protected static ?string $model = CalendarOccupancyMapping::class;

    protected static ?string $navigationLabel = 'Сопоставления занятости';

    protected static ?string $modelLabel = 'Сопоставление';

    protected static ?string $pluralModelLabel = 'Сопоставления';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingCalendars';

    protected static ?int $navigationSort = 31;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

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

        $tenant = currentTenant();

        return SchedulingAdminNavigationPrerequisites::calendarIntegrationsEnabledForTenant($tenant)
            && SchedulingAdminNavigationPrerequisites::tenantHasCalendarSubscriptions($tenant);
    }

    /**
     * Создание записи: нужны интеграции и хотя бы одна подписка на календарь (иначе обязательные поля пустые).
     */
    public static function canStartCreatingMapping(): bool
    {
        return SchedulingAdminNavigationPrerequisites::tenantCanCreateOccupancyMapping(currentTenant());
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = currentTenant()?->id;

        return parent::getEloquentQuery()
            ->whereHas('calendarSubscription.calendarConnection', function (Builder $q) use ($tenantId): void {
                $q->where('scheduling_scope', SchedulingScope::Tenant)
                    ->where('tenant_id', $tenantId);
            });
    }

    public static function form(Schema $schema): Schema
    {
        $tenantId = currentTenant()?->id;

        return $schema
            ->components([
                Section::make('Сопоставление календаря с расписанием')
                    ->description('Связывает события из подключённого календаря с объектом или ресурсом на сайте (занятость, бронирования).')
                    ->schema([
                        Select::make('calendar_subscription_id')
                            ->label('Календарь (подписка)')
                            ->options(fn () => CalendarSubscription::query()
                                ->whereHas('calendarConnection', fn (Builder $q) => $q
                                    ->where('scheduling_scope', SchedulingScope::Tenant)
                                    ->where('tenant_id', $tenantId))
                                ->get()
                                ->mapWithKeys(fn (CalendarSubscription $s): array => [
                                    $s->id => ($s->title ?? $s->external_calendar_id).' (#'.$s->id.')',
                                ]))
                            ->required()
                            ->native(),
                        Select::make('mapping_type')
                            ->label('Тип связи')
                            ->options(self::occupancyMappingTypeOptions())
                            ->required()
                            ->native(),
                        Select::make('scheduling_target_id')
                            ->label('Объект в расписании')
                            ->helperText('Сущность, для которой учитывается занятость (например точка проката).')
                            ->options(fn () => SchedulingTarget::query()
                                ->where('scheduling_scope', SchedulingScope::Tenant)
                                ->where('tenant_id', $tenantId)
                                ->pluck('label', 'id'))
                            ->native()
                            ->nullable(),
                        Select::make('scheduling_resource_id')
                            ->label('Ресурс записи')
                            ->helperText('Если занятость привязана к конкретному ресурсу (место/линия), а не только к объекту.')
                            ->options(fn () => SchedulingResource::query()
                                ->where('scheduling_scope', SchedulingScope::Tenant)
                                ->where('tenant_id', $tenantId)
                                ->pluck('label', 'id'))
                            ->native()
                            ->nullable(),
                        Select::make('match_mode')
                            ->label('Как сопоставлять события')
                            ->options(self::matchModeOptions())
                            ->required()
                            ->native(),
                        Select::make('match_confidence')
                            ->label('Порог уверенности')
                            ->options(self::matchConfidenceOptions())
                            ->required()
                            ->native(),
                        Toggle::make('is_active')->label('Активно')->default(true),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function occupancyMappingTypeOptions(): array
    {
        return [
            OccupancyMappingType::CalendarToTarget->value => 'События календаря → объект в расписании',
            OccupancyMappingType::CalendarToResource->value => 'События календаря → ресурс записи',
            OccupancyMappingType::EventRuleToTarget->value => 'Правило события → объект в расписании',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function matchModeOptions(): array
    {
        return [
            MatchMode::EntireCalendar->value => 'Весь календарь целиком',
            MatchMode::SummaryContains->value => 'По тексту события (вхождение)',
            MatchMode::SummaryRegex->value => 'По тексту события (шаблон)',
            MatchMode::LocationContains->value => 'По полю «место»',
            MatchMode::ManualAssignment->value => 'Только вручную',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function matchConfidenceOptions(): array
    {
        return [
            MatchConfidence::Exact->value => 'Точное совпадение',
            MatchConfidence::High->value => 'Высокая уверенность',
            MatchConfidence::Medium->value => 'Средняя',
            MatchConfidence::ManualReviewRequired->value => 'Нужна проверка менеджером',
        ];
    }

    public static function table(Table $table): Table
    {
        $canCreate = static::canStartCreatingMapping();
        $emptyDescription = $canCreate
            ? 'Календарь → цель или ресурс, чтобы внешняя занятость учитывалась на сайте. Сбросьте поиск/фильтры, если список пуст без причины.'
            : 'Сначала включите интеграции и добавьте подписку на календарь в «Календари (подключения)». Подсказка — «?» в шапке.';

        return AdminEmptyState::applyInitial(
            $table
                ->columns([
                    TextColumn::make('calendarSubscription.title')->label('Календарь')->placeholder('—'),
                    TextColumn::make('mapping_type')
                        ->label('Тип связи')
                        ->formatStateUsing(function (mixed $state): string {
                            $key = $state instanceof OccupancyMappingType
                                ? $state->value
                                : (string) ($state ?? '');

                            return $key !== ''
                                ? (self::occupancyMappingTypeOptions()[$key] ?? $key)
                                : '—';
                        }),
                    TextColumn::make('match_mode')
                        ->label('Сопоставление')
                        ->formatStateUsing(function (mixed $state): string {
                            $key = $state instanceof MatchMode
                                ? $state->value
                                : (string) ($state ?? '');

                            return $key !== ''
                                ? (self::matchModeOptions()[$key] ?? $key)
                                : '—';
                        }),
                ])
                ->actions([EditAction::make()])
                ->bulkActions([
                    BulkActionGroup::make([
                        AdminFilamentDelete::makeBulkDeleteAction(),
                    ]),
                ]),
            'Сопоставлений пока нет',
            $emptyDescription,
            'heroicon-o-arrows-right-left',
            $canCreate ? [CreateAction::make()->label('Добавить сопоставление')] : [],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalendarOccupancyMappings::route('/'),
            'create' => Pages\CreateCalendarOccupancyMapping::route('/create'),
            'edit' => Pages\EditCalendarOccupancyMapping::route('/{record}/edit'),
        ];
    }
}
