<?php

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'pagesWhatIs',
                [
                    'Страницы публичного сайта: slug, основной текст, блоки конструктора и SEO.',
                    '',
                    'Главная (home) собирается из секций на вкладке «Блоки страницы».',
                ],
                'Справка по страницам сайта',
            ),
            CreateAction::make(),
        ];
    }
}
