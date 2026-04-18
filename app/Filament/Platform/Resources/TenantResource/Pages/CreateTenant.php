<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Platform\TenantPlanCreationNotifications;
use App\Models\Plan;
use App\Services\Tenancy\TenantProvisioningService;
use App\Support\TenantRegionalContract;
use App\Support\TenantSlug;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = TenantSlug::normalize(Str::slug($data['name']));
        } elseif (! empty($data['slug'])) {
            $data['slug'] = TenantSlug::normalize((string) $data['slug']);
        }

        if (! empty($data['slug']) && TenantSlug::isNormalizedSlugTaken((string) $data['slug'])) {
            throw ValidationException::withMessages([
                'slug' => 'Такой URL-идентификатор уже занят (после нормализации он совпадает с существующим клиентом).',
            ]);
        }

        if (isset($data['locale'])) {
            $loc = TenantRegionalContract::normalizeLocale((string) $data['locale']);
            if ($loc === null || ! TenantRegionalContract::isValidLocale($loc)) {
                throw ValidationException::withMessages([
                    'locale' => 'Укажите корректную локаль (например ru или en-US).',
                ]);
            }
            $data['locale'] = $loc;
        }

        if (isset($data['currency'])) {
            $cur = TenantRegionalContract::normalizeCurrency((string) $data['currency']);
            if ($cur === null || ! TenantRegionalContract::isValidCurrency($cur)) {
                throw ValidationException::withMessages([
                    'currency' => 'Укажите трёхбуквенный код валюты ISO 4217 (например RUB).',
                ]);
            }
            $data['currency'] = $cur;
        }

        if (array_key_exists('country', $data) && $data['country'] !== null && $data['country'] !== '') {
            $data['country'] = TenantRegionalContract::normalizeCountry((string) $data['country']);
            if (! TenantRegionalContract::isValidCountryOrEmpty($data['country'])) {
                throw ValidationException::withMessages([
                    'country' => 'Страна: двухбуквенный код ISO 3166-1 (например RU) или оставьте пустым.',
                ]);
            }
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

        $record = $this->record;
        DB::afterCommit(function () use ($record, $templateId): void {
            try {
                app(TenantProvisioningService::class)->bootstrapAfterTenantCreated($record, $templateId);
            } catch (\Throwable $e) {
                Log::error('tenant_provisioning_failed_after_create_tenant', [
                    'tenant_id' => $record->id,
                    'exception' => $e,
                ]);
                throw $e;
            }
        });
    }
}
