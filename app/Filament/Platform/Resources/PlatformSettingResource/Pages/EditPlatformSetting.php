<?php

namespace App\Filament\Platform\Resources\PlatformSettingResource\Pages;

use App\Filament\Platform\Resources\PlatformSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditPlatformSetting extends EditRecord
{
    protected static string $resource = PlatformSettingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['type'] ?? '') === 'boolean') {
            $data['value_boolean'] = filter_var($data['value'] ?? '0', FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? '') === 'boolean') {
            $data['value'] = ! empty($data['value_boolean']) ? '1' : '0';
        }

        if (($data['type'] ?? '') === 'integer' && isset($data['value'])) {
            $data['value'] = (string) (int) preg_replace('/\D/', '', (string) $data['value']);
        }

        unset($data['value_boolean'], $data['use_custom_key'], $data['registry_key']);

        return $data;
    }
}
