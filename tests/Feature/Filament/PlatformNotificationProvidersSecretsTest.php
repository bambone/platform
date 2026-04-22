<?php

namespace Tests\Feature\Filament;

use App\Filament\Platform\Pages\PlatformNotificationProvidersPage;
use App\Filament\Platform\Pages\TenantsPushPwaPage;
use App\Models\User;
use App\Services\Platform\PlatformNotificationSettings;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformNotificationProvidersSecretsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_mount_never_prefills_encrypted_secrets_into_form_state(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        app(PlatformNotificationSettings::class)->setTelegramBotToken('plain-secret-token-value');
        app(PlatformNotificationSettings::class)->setVapidKeypair('BKpubsample', 'private-plain');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->assertSet('data.telegram_bot_token', '')
            ->assertSet('data.vapid_private', '');
    }

    /**
     * Ссылка в подсказке про OneSignal/PWA должна вести на существующую platform-страницу.
     */
    public function test_tenants_push_pwa_page_url_is_routable(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        $url = TenantsPushPwaPage::getUrl();

        $this->assertNotSame('', $url);
        $this->assertStringContainsString('tenants-push-pwa', $url);
    }
}
