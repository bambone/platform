<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Models\TemplatePreset;
use App\Models\TenantDomain;
use App\Services\TemplateCloningService;
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

        TenantDomain::create([
            'tenant_id' => $this->record->id,
            'host' => $this->record->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST) ?: $this->record->slug.'.localhost',
            'type' => 'subdomain',
            'is_primary' => true,
            'verification_status' => 'verified',
        ]);
    }
}
