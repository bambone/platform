<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Models\TemplatePreset;
use App\Services\Seo\InitializeTenantSeoDefaults;
use App\Services\TemplateCloningService;
use App\Services\Tenancy\TenantDomainService;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Filament\Resources\Pages\CreateRecord;
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

        return $data;
    }

    protected function afterCreate(): void
    {
        $formState = $this->form->getState();
        $templateId = $formState['template_preset_id'] ?? null;
        if ($templateId) {
            $preset = TemplatePreset::find($templateId);
            if ($preset) {
                app(TemplateCloningService::class)->cloneToTenant($this->record, $preset);
            }
        }

        app(TenantDomainService::class)->createDefaultSubdomain($this->record, $this->record->slug);

        app(TenantStorageQuotaService::class)->ensureQuotaRecord($this->record);

        app(InitializeTenantSeoDefaults::class)->execute($this->record, false, false);
    }
}
