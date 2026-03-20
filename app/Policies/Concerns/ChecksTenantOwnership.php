<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksTenantOwnership
{
    protected function belongsToCurrentTenant(?Model $model): bool
    {
        if (! $model || ! isset($model->tenant_id)) {
            return true;
        }

        $tenant = \currentTenant();
        if (! $tenant) {
            return false;
        }

        return (int) $model->tenant_id === (int) $tenant->id;
    }

    protected function userCanAccessTenant(User $user, ?Model $model): bool
    {
        if (! $model || ! isset($model->tenant_id)) {
            return true;
        }

        return $user->tenants()->where('tenant_id', $model->tenant_id)->exists();
    }
}
