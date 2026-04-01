<?php

namespace Tests\Unit;

use App\Filament\Tenant\Pages\Settings;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantSettingsFormMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_field_map_matches_get_settings_read_keys(): void
    {
        $expected = [
            'general_site_name' => 'general.site_name',
            'general_domain' => 'general.domain',
            'branding_logo' => 'branding.logo',
            'branding_logo_path' => 'branding.logo_path',
            'branding_primary_color' => 'branding.primary_color',
            'branding_favicon' => 'branding.favicon',
            'branding_favicon_path' => 'branding.favicon_path',
            'branding_hero' => 'branding.hero',
            'branding_hero_path' => 'branding.hero_path',
            'contacts_phone' => 'contacts.phone',
            'contacts_phone_alt' => 'contacts.phone_alt',
            'contacts_email' => 'contacts.email',
            'contacts_whatsapp' => 'contacts.whatsapp',
            'contacts_telegram' => 'contacts.telegram',
            'contacts_address' => 'contacts.address',
            'contacts_hours' => 'contacts.hours',
            'seo_robots_txt' => 'seo.robots_txt',
        ];

        $this->assertSame($expected, Settings::formFieldToSettingKeyMap());
    }

    public function test_branding_logo_path_persists_under_branding_logo_path_not_nested_dots(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Map T',
            'slug' => 'mapt',
            'status' => 'active',
        ]);

        $expectedPath = TenantStorage::for($tenant)->publicPath('site/logo/file.png');
        TenantSetting::setForTenant($tenant->id, 'branding.logo_path', $expectedPath);
        Cache::flush();

        $this->assertSame(
            $expectedPath,
            TenantSetting::getForTenant($tenant->id, 'branding.logo_path', '')
        );

        $wrongGroupRow = TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('group', 'branding.logo')
            ->where('key', 'path')
            ->first();
        $this->assertNull($wrongGroupRow, 'old buggy key would have used group branding.logo, key path');
    }

    public function test_general_site_name_and_contacts_phone_alt_keys_round_trip(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Map T2',
            'slug' => 'mapt2',
            'status' => 'active',
        ]);

        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'My Site');
        TenantSetting::setForTenant($tenant->id, 'contacts.phone_alt', '+7999');
        Cache::flush();

        $this->assertSame('My Site', TenantSetting::getForTenant($tenant->id, 'general.site_name', ''));
        $this->assertSame('+7999', TenantSetting::getForTenant($tenant->id, 'contacts.phone_alt', ''));
    }
}
