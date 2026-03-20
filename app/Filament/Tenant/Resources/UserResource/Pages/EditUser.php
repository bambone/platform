<?php

namespace App\Filament\Tenant\Resources\UserResource\Pages;

use App\Filament\Tenant\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tenant = currentTenant();
        if ($tenant) {
            $row = $this->record->tenants()->where('tenant_id', $tenant->id)->first();
            $data['tenant_role'] = $row?->pivot->role ?? 'operator';
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['tenant_role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $tenant = currentTenant();
        if (! $tenant) {
            return;
        }

        $role = $this->form->getState()['tenant_role'] ?? 'operator';

        if ($this->record->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $tenant->users()->updateExistingPivot($this->record->id, [
                'role' => $role,
                'status' => 'active',
            ]);
        } else {
            $tenant->users()->attach($this->record->id, [
                'role' => $role,
                'status' => 'active',
            ]);
        }
    }
}
