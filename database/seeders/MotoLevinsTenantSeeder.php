<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantDomainService;
use Illuminate\Database\Seeder;

class MotoLevinsTenantSeeder extends Seeder
{
    public function run(): void
    {
        $planId = Plan::query()->value('id');

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'motolevins'],
            [
                'name' => 'Moto Levins',
                'brand_name' => 'Moto Levins',
                'theme_key' => 'moto',
                'status' => 'active',
                'timezone' => 'Europe/Moscow',
                'locale' => 'ru',
                'currency' => 'RUB',
                'plan_id' => $planId,
            ]
        );

        $publicUrl = rtrim((string) (env('TENANT_MOTOLEVINS_PUBLIC_URL') ?: config('app.url')), '/');

        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'Moto Levins');
        TenantSetting::setForTenant($tenant->id, 'general.domain', $publicUrl);
        TenantSetting::setForTenant($tenant->id, 'contacts.phone', '+7 (913) 060-86-89');
        TenantSetting::setForTenant($tenant->id, 'contacts.phone_alt', '');
        TenantSetting::setForTenant($tenant->id, 'contacts.whatsapp', '79130608689');
        TenantSetting::setForTenant($tenant->id, 'contacts.telegram', 'motolevins');
        TenantSetting::setForTenant($tenant->id, 'contacts.email', '');
        TenantSetting::setForTenant($tenant->id, 'contacts.address', '');
        TenantSetting::setForTenant($tenant->id, 'contacts.hours', '');
        TenantSetting::setForTenant($tenant->id, 'branding.primary_color', '#E85D04');

        $hosts = array_filter([
            config('app.tenant_default_host'),
            app()->environment('local') ? 'localhost' : null,
            app()->environment('local') ? '127.0.0.1' : null,
        ]);

        foreach ($hosts as $index => $host) {
            $normalized = TenantDomain::normalizeHost((string) $host);
            if ($normalized === '') {
                continue;
            }

            if (TenantDomain::where('host', $normalized)->exists()) {
                continue;
            }

            TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'host' => $normalized,
                'type' => TenantDomain::TYPE_SUBDOMAIN,
                'is_primary' => $index === 0 && ! $tenant->domains()->exists(),
                'status' => TenantDomain::STATUS_ACTIVE,
                'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
                'verified_at' => now(),
                'activated_at' => now(),
            ]);
        }

        app(TenantDomainService::class)->createDefaultSubdomain($tenant, $tenant->slug);
    }
}
