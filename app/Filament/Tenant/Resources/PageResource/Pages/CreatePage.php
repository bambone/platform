<?php

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Filament\Tenant\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
