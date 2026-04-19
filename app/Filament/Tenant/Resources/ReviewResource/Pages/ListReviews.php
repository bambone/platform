<?php

namespace App\Filament\Tenant\Resources\ReviewResource\Pages;

use App\Filament\Tenant\Resources\ReviewResource;
use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'reviewsWhatIs',
                [
                    'Отзывы клиентов: модерация и публикация на сайте.',
                    '',
                    'Настройки формы — в параметрах сайта и теме.',
                ],
                'Справка по отзывам',
            ),
            CreateAction::make(),
        ];
    }
}
