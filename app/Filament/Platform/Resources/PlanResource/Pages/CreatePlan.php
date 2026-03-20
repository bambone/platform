<?php

namespace App\Filament\Platform\Resources\PlanResource\Pages;

use App\Filament\Platform\Resources\PlanResource;
use App\Filament\Platform\Resources\PlanResource\Pages\Concerns\NormalizesPlanJsonForm;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    use NormalizesPlanJsonForm;

    protected static string $resource = PlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizePlanFormData($data);
    }
}
