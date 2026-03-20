<?php

namespace App\Filament\Tenant\Resources\LeadResource\Pages;

use App\Filament\Exports\LeadExporter;
use App\Filament\Tenant\Resources\LeadResource;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string|Htmlable
    {
        return new HtmlString(
            '<div>'
            .'<span class="text-xl font-semibold tracking-tight">Заявки</span>'
            .'<p class="mt-2 max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-400">'
            .'Входящие обращения с сайта: потенциальные клиенты и запросы на аренду. Обрабатывайте новые заявки в первую очередь — '
            .'статус и ответственный видны только вашей команде.'
            .'</p>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(LeadExporter::class)
                ->visible(fn () => Gate::allows('export_leads')),
        ];
    }
}
