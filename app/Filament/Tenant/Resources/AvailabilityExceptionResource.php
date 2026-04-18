<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Tenant\Resources\AvailabilityExceptionResource\Pages;
use App\Models\AvailabilityException;
use App\Models\BookableService;
use App\Models\SchedulingResource;
use App\Models\SchedulingTarget;
use App\Scheduling\Enums\AvailabilityExceptionType;
use App\Scheduling\Enums\SchedulingScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class AvailabilityExceptionResource extends Resource
{
    protected static ?string $model = AvailabilityException::class;

    protected static ?string $navigationLabel = 'Исключения расписания';

    protected static ?string $modelLabel = 'Исключение';

    protected static ?string $pluralModelLabel = 'Исключения';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingAvailability';

    protected static ?int $navigationSort = 21;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

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
            ->whereHas('schedulingResource', function (Builder $q) use ($tenantId): void {
                $q->where('scheduling_scope', SchedulingScope::Tenant)
                    ->where('tenant_id', $tenantId);
            });
    }

    public static function form(Schema $schema): Schema
    {
        $types = [];
        foreach (AvailabilityExceptionType::cases() as $c) {
            $types[$c->value] = $c->value;
        }

        $tid = currentTenant()?->id;

        return $schema
            ->components([
                Section::make('Исключение')
                    ->schema([
                        Select::make('scheduling_resource_id')
                            ->label('Ресурс')
                            ->options(fn () => SchedulingResource::query()
                                ->where('scheduling_scope', SchedulingScope::Tenant)
                                ->where('tenant_id', $tid)
                                ->pluck('label', 'id'))
                            ->required()
                            ->native(),
                        Select::make('scheduling_target_id')
                            ->label('Поверхность (target)')
                            ->options(fn () => SchedulingTarget::query()
                                ->where('scheduling_scope', SchedulingScope::Tenant)
                                ->where('tenant_id', $tid)
                                ->pluck('label', 'id'))
                            ->native()
                            ->nullable(),
                        Select::make('bookable_service_id')
                            ->label('Услуга')
                            ->options(fn () => BookableService::query()
                                ->where('scheduling_scope', SchedulingScope::Tenant)
                                ->where('tenant_id', $tid)
                                ->pluck('title', 'id'))
                            ->native()
                            ->nullable(),
                        Select::make('exception_type')->label('Тип')->options($types)->required()->native(),
                        DateTimePicker::make('starts_at_utc')->label('Начало (UTC)')->required()->seconds(false),
                        DateTimePicker::make('ends_at_utc')->label('Конец (UTC)')->required()->seconds(false),
                        Textarea::make('reason')->label('Причина'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schedulingResource.label')->label('Ресурс'),
                TextColumn::make('exception_type')->label('Тип'),
                TextColumn::make('starts_at_utc')->dateTime()->label('С'),
                TextColumn::make('ends_at_utc')->dateTime()->label('По'),
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
            'index' => Pages\ListAvailabilityExceptions::route('/'),
            'create' => Pages\CreateAvailabilityException::route('/create'),
            'edit' => Pages\EditAvailabilityException::route('/{record}/edit'),
        ];
    }
}
