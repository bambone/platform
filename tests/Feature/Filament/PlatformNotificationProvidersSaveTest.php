<?php

namespace Tests\Feature\Filament;

use App\Filament\Platform\Pages\PlatformNotificationProvidersPage;
use App\Models\User;
use App\Services\Platform\PlatformNotificationSettings;
use App\Services\Platform\VapidKeyPairGenerator;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformNotificationProvidersSaveTest extends TestCase
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

    public function test_save_does_not_partially_apply_when_vapid_public_without_private_and_no_stored_private(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('email', true);
        $settings->setChannelEnabled('telegram', false);
        $settings->setChannelEnabled('webhook', true);
        $settings->setChannelEnabled('web_push', true);
        $settings->setChannelEnabled('web_push_onesignal', true);
        $settings->setTelegramBotToken('keep-token');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => true,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '',
                'vapid_public' => 'BKinvalidPublicWithoutPrivate',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $fresh = app(PlatformNotificationSettings::class);
        $this->assertFalse($fresh->isChannelEnabled('telegram'));
        $this->assertSame('keep-token', $fresh->telegramBotTokenDecrypted());
    }

    public function test_save_clears_telegram_token_when_clear_toggle_enabled(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('telegram', true);
        $settings->setTelegramBotToken('delete-me');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => true,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => true,
                'platform_contact_chat_ids' => '',
                'vapid_public' => '',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $this->assertNull(app(PlatformNotificationSettings::class)->telegramBotTokenDecrypted());
    }

    public function test_save_rejects_new_public_without_private_when_private_already_stored(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('email', true);
        $settings->setVapidKeypair('BKexistingPublic', 'stored-private');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => false,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '',
                'vapid_public' => 'BKnewPublic',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $this->assertSame('BKexistingPublic', app(PlatformNotificationSettings::class)->vapidPublicKey());
        $this->assertSame('stored-private', app(PlatformNotificationSettings::class)->vapidPrivateKeyDecrypted());
    }

    public function test_save_applies_other_fields_when_legacy_vapid_public_only_unchanged(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('email', true);
        $settings->setChannelEnabled('telegram', false);
        $settings->setVapidKeypair('BKlegacyPublicOnly', null);

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => true,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '',
                'vapid_public' => 'BKlegacyPublicOnly',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $fresh = app(PlatformNotificationSettings::class);
        $this->assertTrue($fresh->isChannelEnabled('telegram'));
        $this->assertSame('BKlegacyPublicOnly', $fresh->vapidPublicKey());
        $this->assertNull($fresh->vapidPrivateKeyDecrypted());
    }

    public function test_save_clears_vapid_when_toggle_enabled(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('email', true);
        $settings->setVapidKeypair('BKpub', 'priv-secret');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => false,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '',
                'vapid_public' => 'BKignored',
                'vapid_private' => 'ignored',
                'clear_vapid_keys' => true,
            ])
            ->call('save');

        $fresh = app(PlatformNotificationSettings::class);
        $this->assertNull($fresh->vapidPublicKey());
        $this->assertNull($fresh->vapidPrivateKeyDecrypted());
    }

    public function test_save_rejects_cleared_public_field_without_vapid_reset_toggle(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setVapidKeypair('BKpub', 'priv-secret');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => false,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '',
                'vapid_public' => '',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $fresh = app(PlatformNotificationSettings::class);
        $this->assertSame('BKpub', $fresh->vapidPublicKey());
        $this->assertSame('priv-secret', $fresh->vapidPrivateKeyDecrypted());
    }

    public function test_generate_vapid_section_action_persists_new_keypair(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $this->mock(VapidKeyPairGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'public' => 'BKtestMockPublic',
                    'private' => 'testMockPrivate',
                ]);
        });

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->callAction([
                'name' => 'generateVapidKeypair',
                'context' => ['schemaComponent' => 'form.web_push_vapid_section.vapid_keygen_row'],
            ])
            ->assertSet('lastVapidKeypairOutcome', 'Новая пара VAPID ключей сгенерирована и сохранена.');

        $fresh = app(PlatformNotificationSettings::class);
        $this->assertSame('BKtestMockPublic', $fresh->vapidPublicKey());
        $this->assertSame('testMockPrivate', $fresh->vapidPrivateKeyDecrypted());
    }

    public function test_generate_vapid_section_action_overwrites_existing_after_confirm(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        app(PlatformNotificationSettings::class)->setVapidKeypair('BKpubsample', 'priv-plain');

        $this->mock(VapidKeyPairGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'public' => 'BKtestMockPublicReplaced',
                    'private' => 'testMockPrivateReplaced',
                ]);
        });

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->call('mountAction', 'generateVapidKeypair', [], ['schemaComponent' => 'form.web_push_vapid_section.vapid_keygen_row'])
            ->call('callMountedAction')
            ->assertSet('lastVapidKeypairOutcome', 'Новая пара VAPID ключей сгенерирована и сохранена.');

        $fresh = app(PlatformNotificationSettings::class);
        $this->assertSame('BKtestMockPublicReplaced', $fresh->vapidPublicKey());
        $this->assertSame('testMockPrivateReplaced', $fresh->vapidPrivateKeyDecrypted());
    }

    public function test_save_reports_telegram_token_outcomes(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        $first = Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => true,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => 'new-token',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '',
                'vapid_public' => '',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $first->assertSet('lastTelegramTokenOutcome', 'Токен сохранён');

        $second = Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => true,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => 'replaced',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '',
                'vapid_public' => '',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $second->assertSet('lastTelegramTokenOutcome', 'Токен обновлён');
    }

    public function test_save_rejects_chat_ids_when_any_token_starts_with_at(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('email', true);
        $settings->setPlatformContactTelegramChatIds('111');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => true,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '12345, @gman1990_bot',
                'vapid_public' => '',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $this->assertSame('111', app(PlatformNotificationSettings::class)->platformContactTelegramChatIdsRaw());
    }

    public function test_save_rejects_chat_ids_when_any_token_is_not_numeric_chat_id(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('email', true);
        $settings->setPlatformContactTelegramChatIds('111');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(PlatformNotificationProvidersPage::class)
            ->fillForm([
                'channel_email_enabled' => true,
                'channel_telegram_enabled' => true,
                'channel_webhook_enabled' => true,
                'channel_web_push_enabled' => true,
                'channel_web_push_onesignal_enabled' => true,
                'telegram_bot_token' => '',
                'clear_telegram_bot_token' => false,
                'platform_contact_chat_ids' => '12345, foo',
                'vapid_public' => '',
                'vapid_private' => '',
                'clear_vapid_keys' => false,
            ])
            ->call('save');

        $this->assertSame('111', app(PlatformNotificationSettings::class)->platformContactTelegramChatIdsRaw());
    }
}
