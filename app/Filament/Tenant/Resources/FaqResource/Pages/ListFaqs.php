<?php

namespace App\Filament\Tenant\Resources\FaqResource\Pages;

use App\Filament\Tenant\Resources\FaqResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaqs extends ListRecords
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'faqsWhatIs',
                [
                    'Вопросы и ответы для блока FAQ на сайте.',
                    '',
                    'Порядок и видимость зависят от темы и секций страницы.',
                ],
                'Справка по FAQ',
            ),
            CreateAction::make(),
        ];
    }
}
