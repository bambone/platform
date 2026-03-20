<?php

namespace App\Filament\Tenant\Resources\FaqResource\Pages;

use App\Filament\Tenant\Resources\FaqResource;
use Filament\Resources\Pages\ListRecords;

class ListFaqs extends ListRecords
{
    protected static string $resource = FaqResource::class;
}
