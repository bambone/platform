<?php

namespace App\Filament\Platform\Resources\PlatformSettingResource\Pages;

use App\Filament\Platform\Resources\PlatformSettingResource;
use App\Filament\Support\PlatformSettingRegistry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

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

        $usingCustomKey = ! empty($data['use_custom_key']);

        unset($data['registry_key'], $data['use_custom_key'], $data['value_boolean']);

        $key = isset($data['key']) ? trim((string) $data['key']) : '';
        if ($key === '') {
            $field = $usingCustomKey ? 'key' : 'registry_key';
            $message = $usingCustomKey
                ? 'Укажите системный ключ.'
                : 'Выберите параметр из списка.';

            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        if (preg_match('/^[a-zA-Z0-9._-]+$/', $key) !== 1) {
            throw ValidationException::withMessages([
                'key' => 'Ключ может содержать только латиницу, цифры, точки, подчёркивания и дефисы.',
            ]);
        }

        $data['key'] = $key;

        return $data;
    }
}
