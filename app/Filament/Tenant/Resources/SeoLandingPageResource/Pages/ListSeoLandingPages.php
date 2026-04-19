<?php

namespace App\Filament\Tenant\Resources\SeoLandingPageResource\Pages;

use App\Filament\Tenant\Resources\SeoLandingPageResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoLandingPages extends ListRecords
{
    protected static string $resource = SeoLandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'seoLandingPagesWhatIs',
                [
                    'SEO-лендинги под поисковые запросы: отдельные URL и контент вне основного меню.',
                    '',
                    'Подключение на сайте зависит от темы и навигации.',
                ],
                'Справка по SEO-лендингам',
            ),
            CreateAction::make(),
        ];
    }
}
