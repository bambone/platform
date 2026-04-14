<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CalendarOccupancyMappingResource\Pages;
use App\Models\CalendarOccupancyMapping;
use App\Models\CalendarSubscription;
use App\Models\SchedulingResource;
use App\Models\SchedulingTarget;
use App\Scheduling\Enums\MatchConfidence;
use App\Scheduling\Enums\MatchMode;
use App\Scheduling\Enums\OccupancyMappingType;
use App\Scheduling\Enums\SchedulingScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
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

    protected static string|UnitEnum|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 65;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
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

        $mapTypes = [];
        foreach (OccupancyMappingType::cases() as $c) {
            $mapTypes[$c->value] = $c->value;
        }
        $modes = [];
        foreach (MatchMode::cases() as $c) {
            $modes[$c->value] = $c->value;
        }
        $conf = [];
        foreach (MatchConfidence::cases() as $c) {
            $conf[$c->value] = $c->value;
        }

        return $schema
            ->components([
                Section::make('Маппинг')
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
                        Select::make('mapping_type')->label('Тип')->options($mapTypes)->required()->native(),
                        Select::make('scheduling_target_id')
                            ->label('Target')
                            ->options(fn () => SchedulingTarget::query()
                                ->where('scheduling_scope', SchedulingScope::Tenant)
                                ->where('tenant_id', $tenantId)
                                ->pluck('label', 'id'))
                            ->native()
                            ->nullable(),
                        Select::make('scheduling_resource_id')
                            ->label('Ресурс')
                            ->options(fn () => SchedulingResource::query()
                                ->where('scheduling_scope', SchedulingScope::Tenant)
                                ->where('tenant_id', $tenantId)
                                ->pluck('label', 'id'))
                            ->native()
                            ->nullable(),
                        Select::make('match_mode')->label('Режим сопоставления')->options($modes)->required()->native(),
                        Select::make('match_confidence')->label('Уверенность')->options($conf)->required()->native(),
                        Toggle::make('is_active')->label('Активно')->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calendarSubscription.title')->label('Календарь')->placeholder('—'),
                TextColumn::make('mapping_type')->label('Тип'),
                TextColumn::make('match_mode')->label('Режим'),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
