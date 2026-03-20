<?php

namespace App\Filament\Platform\Resources\PlatformSettingResource\Pages;

use App\Filament\Platform\Resources\PlatformSettingResource;
use App\Filament\Support\PlatformSettingRegistry;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformSetting extends CreateRecord
{
    protected static string $resource = PlatformSettingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['use_custom_key']) && ! empty($data['registry_key'])) {
            $data['key'] = $data['registry_key'];
            $def = PlatformSettingRegistry::definition($data['key']);
            if ($def !== null) {
                $data['type'] = $def['type'];
            }
        }

        if (($data['type'] ?? '') === 'boolean') {
            $data['value'] = ! empty($data['value_boolean']) ? '1' : '0';
        }

        if (($data['type'] ?? '') === 'integer' && isset($data['value'])) {
            $data['value'] = (string) (int) preg_replace('/\D/', '', (string) $data['value']);
        }

        unset($data['registry_key'], $data['use_custom_key'], $data['value_boolean']);

        return $data;
    }
}
