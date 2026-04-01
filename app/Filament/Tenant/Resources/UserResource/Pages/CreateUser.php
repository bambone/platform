<?php

namespace App\Filament\Tenant\Resources\UserResource\Pages;

use App\Auth\TenantMembershipRoleHierarchy;
use App\Filament\Tenant\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterValidate(): void
    {
        $tenant = currentTenant();
        $actor = Auth::user();
        if (! $tenant || ! $actor instanceof User) {
            return;
        }

        $role = $this->form->getState()['tenant_role'] ?? null;
        if (! is_string($role)) {
            throw ValidationException::withMessages([
                'tenant_role' => 'Выберите роль участника.',
            ]);
        }

        $allowed = TenantMembershipRoleHierarchy::allowedRoleKeysForAssignment(
            $actor,
            null,
            (int) $tenant->id,
            true
        );

        if (! in_array($role, $allowed, true)) {
            throw ValidationException::withMessages([
                'tenant_role' => 'Эту роль нельзя назначить при создании участника с вашей ролью.',
            ]);
        }
    }

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
