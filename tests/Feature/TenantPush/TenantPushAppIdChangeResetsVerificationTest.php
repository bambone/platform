<?php

namespace Tests\Feature\TenantPush;

use App\Models\TenantPushSettings;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushProviderStatus;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Смена App ID без нового ключа сбрасывает доверие к проверке ({@see \App\Filament\Tenant\Pages\TenantPushPwaSettingsPage::save}).
 */
class TenantPushAppIdChangeResetsVerificationTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_changing_onesignal_app_id_without_new_key_sets_invalid_and_pending(): void
    {
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithActiveDomain('appidchg');
        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->onesignal_app_id = 'old-app-id';
        $settings->provider_status = TenantPushProviderStatus::Verified->value;
        $settings->onesignal_config_verified_at = now();
        $settings->onesignal_key_pending_verification = false;
        $settings->onesignal_last_verification_error = 'stale';
        $settings->save();

        $settings = TenantPushSettings::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $previousAppId = strtolower(trim((string) ($settings->onesignal_app_id ?? '')));
        $newAppIdTrimmed = trim('new-app-id');
        $newAppId = $newAppIdTrimmed !== '' ? $newAppIdTrimmed : null;
        $newAppIdNorm = $newAppId !== null ? strtolower($newAppId) : '';
        $key = '';

        $settings->fill([
            'onesignal_app_id' => $newAppId,
        ]);

        if ($key !== '') {
            $settings->onesignal_app_api_key_encrypted = $key;
            $settings->onesignal_key_pending_verification = true;
            $settings->provider_status = TenantPushProviderStatus::Invalid->value;
        } elseif ($previousAppId !== $newAppIdNorm) {
            $settings->provider_status = TenantPushProviderStatus::Invalid->value;
            $settings->onesignal_key_pending_verification = true;
            $settings->onesignal_config_verified_at = null;
            $settings->onesignal_last_verification_error = null;
        }

        $settings->save();

        $settings->refresh();
        $this->assertSame(TenantPushProviderStatus::Invalid->value, $settings->provider_status);
        $this->assertTrue($settings->onesignal_key_pending_verification);
        $this->assertNull($settings->onesignal_config_verified_at);
        $this->assertNull($settings->onesignal_last_verification_error);
    }

    public function test_entering_new_api_key_clears_verified_at_and_last_error(): void
    {
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithActiveDomain('newkeyreset');
        $gate = app(TenantPushFeatureGate::class);
        $settings = $gate->ensureSettings($tenant);
        $settings->onesignal_app_id = 'same-app';
        $settings->provider_status = TenantPushProviderStatus::Verified->value;
        $settings->onesignal_config_verified_at = now();
        $settings->onesignal_key_pending_verification = false;
        $settings->onesignal_last_verification_error = 'previous failure text';
        $settings->save();

        $settings->refresh();
        $key = 'new-rest-api-key';

        $settings->onesignal_app_api_key_encrypted = $key;
        $settings->onesignal_key_pending_verification = true;
        $settings->provider_status = TenantPushProviderStatus::Invalid->value;
        $settings->onesignal_config_verified_at = null;
        $settings->onesignal_last_verification_error = null;
        $settings->save();

        $settings->refresh();
        $this->assertNull($settings->onesignal_config_verified_at);
        $this->assertNull($settings->onesignal_last_verification_error);
        $this->assertSame(TenantPushProviderStatus::Invalid->value, $settings->provider_status);
        $this->assertTrue($settings->onesignal_key_pending_verification);
    }
}
