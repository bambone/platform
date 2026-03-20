<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformCreateOwnerCommand extends Command
{
    protected $signature = 'platform:create-owner
                            {--email= : Email address}
                            {--name=Platform Owner : Display name}
                            {--password= : Password (omit: prompt; empty with --no-interaction: generate)}';

    protected $description = 'Create or update a user and assign platform_owner (idempotent by email)';

    public function handle(): int
    {
        $this->call(RolePermissionSeeder::class);

        $email = $this->option('email') ?: $this->ask('Email');
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Valid email is required.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: 'Platform Owner';
        $existing = User::query()->where('email', $email)->first();

        $passwordOption = $this->option('password');
        $plain = null;

        if ($passwordOption !== null && $passwordOption !== '') {
            $plain = $passwordOption;
        } elseif (! $existing) {
            if ($this->option('no-interaction')) {
                $plain = Str::password(20);
                $this->info("Generated password: {$plain}");
            } else {
                $plain = $this->secret('Password (leave empty to generate)');
                if ($plain === null || $plain === '') {
                    $plain = Str::password(20);
                    $this->info("Generated password: {$plain}");
                }
            }
        } elseif ($existing && ! $this->option('no-interaction')) {
            $rotate = $this->confirm('User exists. Rotate password?', false);
            if ($rotate) {
                $plain = $this->secret('New password (leave empty to generate)');
                if ($plain === null || $plain === '') {
                    $plain = Str::password(20);
                    $this->info("Generated password: {$plain}");
                }
            }
        }

        $payload = [
            'name' => $name,
            'status' => 'active',
        ];
        if ($plain !== null) {
            $payload['password'] = Hash::make($plain);
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            $payload
        );

        $user->syncRoles([]);
        $user->assignRole('platform_owner');

        $this->info("Platform owner ready: {$user->email} (role: platform_owner)");

        return self::SUCCESS;
    }
}
