<?php

namespace App\Filament\Tenant\Resources\CrmRequestResource\Pages;

use App\Filament\Tenant\Resources\CrmRequestResource;
use App\Models\CrmRequest;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ViewCrmRequest extends ViewRecord
{
    protected static string $resource = CrmRequestResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var CrmRequest $record */
        $record = static::getResource()::getEloquentQuery()
            ->whereKey($key)
            ->firstOrFail();

        return $record;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.shared.crm.crm-workspace-modal')
                    ->viewData(fn (): array => [
                        'crmRequestId' => (int) $this->getRecord()->getKey(),
                    ]),
            ]);
    }
}
