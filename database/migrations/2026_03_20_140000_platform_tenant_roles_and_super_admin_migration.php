<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $guard = 'web';

        foreach (['platform_owner', 'platform_admin', 'support_manager'] as $name) {
            Role::findOrCreate($name, $guard);
        }

        foreach (['tenant_owner', 'tenant_admin', 'booking_manager', 'fleet_manager', 'content_manager', 'operator'] as $name) {
            Role::findOrCreate($name, $guard);
        }

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', $guard)->first();
        $platformOwner = Role::where('name', 'platform_owner')->where('guard_name', $guard)->first();

        if ($superAdmin && $platformOwner) {
            DB::table('model_has_roles')
                ->where('role_id', $superAdmin->id)
                ->update(['role_id' => $platformOwner->id]);
        }
    }

    public function down(): void
    {
        // Не откатываем назначения ролей автоматически — риск потери доступа.
    }
};
