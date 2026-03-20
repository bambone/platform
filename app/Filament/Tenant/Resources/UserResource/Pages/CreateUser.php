<?php

namespace App\Filament\Tenant\Resources\UserResource\Pages;

use App\Filament\Tenant\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['tenant_role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $tenant = currentTenant();
        if (! $tenant) {
            return;
        }

        $role = $this->form->getState()['tenant_role'] ?? 'operator';

        $tenant->users()->syncWithoutDetaching([
            $this->record->id => [
                'role' => $role,
                'status' => 'active',
            ],
        ]);
    }
}
