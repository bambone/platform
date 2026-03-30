<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\CRM\CrmSharedFilters;
use App\Filament\Shared\CRM\CrmSharedInfolist;
use App\Filament\Shared\CRM\CrmSharedTable;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Resources\CrmRequestResource\Pages;
use App\Models\CrmRequest;
use App\Terminology\DomainTermKeys;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CrmRequestResource extends Resource
{
    use ResolvesDomainTermLabels;

    protected static ?string $model = CrmRequest::class;

    public static function getNavigationLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::REQUEST_PLURAL, 'CRM-заявки');
    }

    public static function getModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::REQUEST, 'CRM-заявка');
    }

    public static function getPluralModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::REQUEST_PLURAL, 'CRM-заявки');
    }

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

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
            ->with('assignedUser');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CrmSharedInfolist::schema($schema);
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
        $user = auth()->user();

        return $user !== null && Gate::forUser($user)->allows('update', $record);
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
