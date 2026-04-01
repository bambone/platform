<?php

namespace App\Filament\Tenant\Resources\UserResource\Pages;

use App\Auth\TenantMembershipRoleHierarchy;
use App\Filament\Concerns\IssuesNewUserPasswordFromForm;
use App\Filament\Tenant\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    use IssuesNewUserPasswordFromForm;

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
            $this->record,
            (int) $tenant->id,
            false
        );

        if (! in_array($role, $allowed, true)) {
            throw ValidationException::withMessages([
                'tenant_role' => 'Эту роль назначить нельзя.',
            ]);
        }

        $oldRole = $this->record->tenants()->where('tenant_id', $tenant->id)->first()?->pivot->role;
        if (is_string($oldRole)) {
            TenantMembershipRoleHierarchy::assertLastOwnerNotDemoted($tenant, $oldRole, $role);
        }
    }

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
        $data = $this->mergeNewPasswordIntoUserData($data);
        unset($data['tenant_role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $tenant = currentTenant();
        if (! $tenant) {
            $this->sendIssuedPasswordMailIfNeeded();

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

        $this->sendIssuedPasswordMailIfNeeded();
    }
}
