<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Tenant\Resources\ManualBusyBlockResource\Pages;
use App\Models\ManualBusyBlock;
use App\Scheduling\Enums\ManualBusySeverity;
use App\Scheduling\Enums\ManualBusySource;
use App\Scheduling\Enums\SchedulingScope;
use App\Tenant\Filament\TenantPanelSelectScope;
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

class ManualBusyBlockResource extends Resource
{
    protected static ?string $model = ManualBusyBlock::class;

    protected static ?string $navigationLabel = 'Ручные блокировки';

    protected static ?string $modelLabel = 'Блокировка';

    protected static ?string $pluralModelLabel = 'Блокировки';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingAvailability';

    protected static ?int $navigationSort = 22;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

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

    public static function form(Schema $schema): Schema
    {
        $sev = [];
        foreach (ManualBusySeverity::cases() as $c) {
            $sev[$c->value] = $c->value;
        }
        $src = [];
        foreach (ManualBusySource::cases() as $c) {
            $src[$c->value] = $c->value;
        }

        return $schema
            ->components([
                Section::make('Блокировка')
                    ->schema([
                        Select::make('scheduling_target_id')
                            ->label('Поверхность (target)')
                            ->relationship(
                                name: 'schedulingTarget',
                                titleAttribute: 'label',
                                modifyQueryUsing: function (Builder $query): void {
                                    $query->where('scheduling_scope', SchedulingScope::Tenant);
                                    TenantPanelSelectScope::applyTenantOwnedScope($query, currentTenant()?->id);
                                },
                            )
                            ->preload()
                            ->native()
                            ->nullable(),
                        Select::make('scheduling_resource_id')
                            ->label('Ресурс')
                            ->relationship(
                                name: 'schedulingResource',
                                titleAttribute: 'label',
                                modifyQueryUsing: function (Builder $query): void {
                                    $query->where('scheduling_scope', SchedulingScope::Tenant);
                                    TenantPanelSelectScope::applyTenantOwnedScope($query, currentTenant()?->id);
                                },
                            )
                            ->preload()
                            ->required(),
                        DateTimePicker::make('starts_at_utc')->label('Начало (UTC)')->required()->seconds(false),
                        DateTimePicker::make('ends_at_utc')->label('Конец (UTC)')->required()->seconds(false),
                        Textarea::make('reason')->label('Причина'),
                        Select::make('severity')->options($sev)->required()->native(),
                        Select::make('source')->options($src)->default(ManualBusySource::Operator->value)->required()->native(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schedulingResource.label')->label('Ресурс'),
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
            'index' => Pages\ListManualBusyBlocks::route('/'),
            'create' => Pages\CreateManualBusyBlock::route('/create'),
            'edit' => Pages\EditManualBusyBlock::route('/{record}/edit'),
        ];
    }
}
