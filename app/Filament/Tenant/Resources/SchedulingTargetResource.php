<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Support\AdminEmptyState;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Tenant\Resources\SchedulingTargetResource\Pages;
use App\Filament\Tenant\Support\SchedulingAdminNavigationPrerequisites;
use App\Models\SchedulingTarget;
use App\Scheduling\Enums\CalendarUsageMode;
use App\Scheduling\Enums\ExternalBusyEffect;
use App\Scheduling\Enums\OccupancyScopeMode;
use App\Scheduling\Enums\SchedulingScope;
use App\Scheduling\Enums\SchedulingTargetType;
use App\Scheduling\Enums\StaleBusyPolicy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class SchedulingTargetResource extends Resource
{
    protected static ?string $model = SchedulingTarget::class;

    protected static ?string $navigationLabel = 'Цели расписания';

    protected static ?string $modelLabel = 'Цель';

    protected static ?string $pluralModelLabel = 'Цели';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingCore';

    protected static ?int $navigationSort = 12;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', currentTenant()?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (! static::$shouldRegisterNavigation) {
            return false;
        }

        $tenant = currentTenant();
        if ($tenant === null) {
            return false;
        }

        if (SchedulingAdminNavigationPrerequisites::tenantHasSchedulingResources($tenant)) {
            return true;
        }

        return SchedulingTarget::query()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    /**
     * Ручное создание цели бессмысленно без календарных ресурсов — нечего выбрать в поле «Ресурсы».
     */
    public static function canStartCreatingTarget(): bool
    {
        return SchedulingAdminNavigationPrerequisites::tenantHasSchedulingResources(currentTenant());
    }

    public static function form(Schema $schema): Schema
    {
        $targetTypes = [];
        foreach (SchedulingTargetType::cases() as $c) {
            $targetTypes[$c->value] = $c->value;
        }
        $usage = [];
        foreach (CalendarUsageMode::cases() as $c) {
            $usage[$c->value] = $c->value;
        }
        $scopeModes = [];
        foreach (OccupancyScopeMode::cases() as $c) {
            $scopeModes[$c->value] = $c->value;
        }
        $effects = [];
        foreach (ExternalBusyEffect::cases() as $c) {
            $effects[$c->value] = $c->value;
        }
        $stale = [];
        foreach (StaleBusyPolicy::cases() as $c) {
            $stale[$c->value] = $c->value;
        }

        return $schema
            ->components([
                Section::make('Цель')
                    ->description(FilamentInlineMarkdown::toHtml(
                        '**Цель** — связка сущности (тип + ID) и **ресурсов расписания** для слотов. Чаще создаётся автоматически с услугей с записью.'
                    ))
                    ->schema([
                        TextInput::make('label')->label('Название')->required(),
                        Select::make('target_type')->label('Тип цели')->options($targetTypes)->required()->native(),
                        TextInput::make('target_id')
                            ->label('ID сущности')
                            ->helperText('Ключ строки в БД для выбранного типа; при сомнениях смотрите карточку услуги или URL редактирования.')
                            ->numeric()
                            ->required(),
                        Toggle::make('scheduling_enabled')->label('Включить выбор времени'),
                        Toggle::make('external_busy_enabled')->label('Учитывать внешние календари'),
                        Toggle::make('internal_busy_enabled')->label('Учитывать внутренние записи'),
                        Toggle::make('auto_write_to_calendar_enabled')->label('Писать события наружу'),
                        Select::make('occupancy_scope_mode')->label('Режим occupancy')->options($scopeModes)->required()->native(),
                        Select::make('calendar_usage_mode')->label('Режим календаря')->options($usage)->required()->native(),
                        Select::make('external_busy_effect')->label('Эффект для аренды/bridge')->options($effects)->required()->native(),
                        Select::make('stale_busy_policy')
                            ->label('Политика устаревшего busy')
                            ->options($stale)
                            ->native()
                            ->nullable(),
                        Toggle::make('is_active')->label('Активна')->default(true),
                        Select::make('schedulingResources')
                            ->label('Ресурсы')
                            ->helperText('Пусто, пока нет записей в «Ресурсы расписания».')
                            ->relationship('schedulingResources', 'label')
                            ->multiple()
                            ->preload(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $canCreate = static::canStartCreatingTarget();
        $emptyDescription = $canCreate
            ? 'Чаще цель создаётся при настройке услуги с записью. Сбросите поиск/фильтры, если список кажется пустым без причины.'
            : 'Сначала добавьте ресурс в «Запись: основа» → «Ресурсы расписания», затем создайте цель.';

        return AdminEmptyState::applyInitial(
            $table
                ->columns([
                    TextColumn::make('label')->label('Название')->searchable(),
                    TextColumn::make('target_type')->label('Тип'),
                    TextColumn::make('target_id')->label('ID'),
                    IconColumn::make('scheduling_enabled')->label('Слоты')->boolean(),
                ])
                ->actions([EditAction::make()])
                ->bulkActions([
                    BulkActionGroup::make([
                        AdminFilamentDelete::makeBulkDeleteAction(),
                    ]),
                ]),
            'Целей расписания пока нет',
            $emptyDescription,
            'heroicon-o-map-pin',
            $canCreate ? [CreateAction::make()->label('Создать цель')] : [],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedulingTargets::route('/'),
            'create' => Pages\CreateSchedulingTarget::route('/create'),
            'edit' => Pages\EditSchedulingTarget::route('/{record}/edit'),
        ];
    }
}
