<?php

namespace App\Filament\Tenant\Resources\LeadResource\Pages;

use App\Filament\Exports\LeadExporter;
use App\Filament\Tenant\Resources\LeadResource;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(LeadExporter::class)
                ->visible(fn () => Gate::allows('export_leads')),
        ];
    }
}
