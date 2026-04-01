<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\CRM\CrmSharedFilters;
use App\Filament\Shared\CRM\CrmSharedTable;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Resources\CrmRequestResource\Pages;
use App\Models\CrmRequest;
use App\Models\User;
use App\Terminology\DomainTermKeys;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CrmRequestResource extends Resource
{
    use ResolvesDomainTermLabels;

    protected static ?string $model = CrmRequest::class;

    protected static ?string $panel = 'admin';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    public static function getNavigationLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::REQUEST_PLURAL, 'Заявки');
    }

    public static function getModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::REQUEST, 'Заявка');
    }

    public static function getPluralModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::REQUEST_PLURAL, 'Заявки');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $tenant = currentTenant();
        $query = parent::getEloquentQuery();
        if ($tenant === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('tenant_id', $tenant->id)
            ->withCount('notes')
            ->with(['assignedUser', 'leads.motorcycle.media']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        // Карточка в кабинете — только Livewire workspace (ViewCrmRequest::content).
        // Пустой infolist: иначе ViewRecord::hasInfolist() true и Filament ждёт infolist-контур параллельно кастомному content.
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(CrmSharedTable::columns())
            ->filters(CrmSharedFilters::tableFilters(static::getEloquentQuery()))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (CrmRequest $record): string => static::getUrl('view', ['record' => $record]))
            ->recordClasses(CrmSharedTable::recordClasses())
            ->paginated([25, 50, 100]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        return $user instanceof User && Gate::forUser($user)->allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmRequests::route('/'),
            'view' => Pages\ViewCrmRequest::route('/{record}'),
        ];
    }
}
