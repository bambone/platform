<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Создаёт администратора для Platform Console и (при наличии) membership в демо-tenant.
     * Запуск: php artisan db:seed --class=AdminUserSeeder
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@motolevins.local');
        $password = env('ADMIN_PASSWORD', 'password');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );

        $user->syncRoles([]);
        $user->assignRole('platform_owner');

        $tenant = Tenant::where('slug', 'motolevins')->first();
        if ($tenant) {
            $tenant->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => 'tenant_owner',
                    'status' => 'active',
                ],
            ]);
            if (empty($tenant->owner_user_id)) {
                $tenant->update(['owner_user_id' => $user->id]);
            }
        }

        $this->command->info("Admin: {$email} / {$password} (platform_owner + tenant motolevins если есть)");
    }
}
