<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Platform\TenantPlanCreationNotifications;
use App\Models\Plan;
use App\Services\Tenancy\TenantProvisioningService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        unset($data['template_preset_id']);

        $planId = isset($data['plan_id']) ? (int) $data['plan_id'] : null;
        if ($planId === null || $planId === 0) {
            $planId = Plan::defaultIdForOnboarding();
        }

        if ($planId === null) {
            TenantPlanCreationNotifications::noActivePlans()->send();

            throw new Halt;
        }

        if (! Plan::query()->where('id', $planId)->where('is_active', true)->exists()) {
            TenantPlanCreationNotifications::selectedPlanInactive()->send();

            throw new Halt;
        }

        $data['plan_id'] = $planId;

        return $data;
    }

    protected function afterCreate(): void
    {
        $formState = $this->form->getState();
        $templateId = isset($formState['template_preset_id']) ? (int) $formState['template_preset_id'] : null;
        $templateId = ($templateId !== null && $templateId > 0) ? $templateId : null;

        app(TenantProvisioningService::class)->bootstrapAfterTenantCreated($this->record, $templateId);
    }
}
