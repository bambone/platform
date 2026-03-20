<?php

namespace App\Console\Commands;

use App\Auth\AccessRoles;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class TenantAttachUserCommand extends Command
{
    protected $signature = 'tenant:attach-user
                            {--email= : User email}
                            {--tenant= : Tenant slug or numeric id}
                            {--role=tenant_owner : Pivot role (see AccessRoles::TENANT_MEMBERSHIP)}
                            {--status=active : Pivot status: active, invited, suspended}';

    protected $description = 'Attach or update tenant_user pivot (upsert by user+tenant, no duplicates)';

    public function handle(): int
    {
        $email = $this->option('email');
        $tenantRef = $this->option('tenant');
        $role = $this->option('role');
        $status = $this->option('status');

        if (! $email || ! $tenantRef) {
            $this->error('Both --email and --tenant are required.');

            return self::FAILURE;
        }

        if (! in_array($role, AccessRoles::TENANT_MEMBERSHIP, true)) {
            $this->error('Invalid --role. Allowed: '.implode(', ', AccessRoles::TENANT_MEMBERSHIP));

            return self::FAILURE;
        }

        if (! in_array($status, ['active', 'invited', 'suspended'], true)) {
            $this->error('Invalid --status. Use: active, invited, suspended');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        $tenant = is_numeric($tenantRef)
            ? Tenant::query()->find($tenantRef)
            : Tenant::query()->where('slug', $tenantRef)->first();

        if (! $tenant) {
            $this->error("Tenant not found: {$tenantRef}");

            return self::FAILURE;
        }

        $pivot = [
            'role' => $role,
            'status' => $status,
        ];

        if ($user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $user->tenants()->updateExistingPivot($tenant->id, $pivot);
            $this->info("Updated tenant_user for {$email} on tenant [{$tenant->slug}]: role={$role}, status={$status}");
        } else {
            $user->tenants()->attach($tenant->id, $pivot);
            $this->info("Attached {$email} to tenant [{$tenant->slug}]: role={$role}, status={$status}");
        }

        return self::SUCCESS;
    }
}
