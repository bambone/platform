<?php

namespace App\Filament\Platform\Resources\PlanResource\Pages;

use App\Filament\Platform\Resources\PlanResource;
use App\Filament\Platform\Resources\PlanResource\Pages\Concerns\NormalizesPlanJsonForm;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    use NormalizesPlanJsonForm;

    protected static string $resource = PlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->expandPlanFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizePlanFormData($data);
    }
}
