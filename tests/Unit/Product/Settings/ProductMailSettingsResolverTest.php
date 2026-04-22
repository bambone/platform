<?php

namespace Tests\Unit\Product\Settings;

use App\Models\PlatformSetting;
use App\Product\Settings\ProductMailSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMailSettingsResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_from_falls_back_to_smtp_username_when_config_from_invalid_or_empty(): void
    {
        config([
            'mail.from.address' => 'not-an-email',
            'mail.mailers.smtp.username' => 'realbox@yandex.test',
            'mail.use_smtp_user_as_platform_from' => false,
        ]);

        $r = new ProductMailSettingsResolver;

        $this->assertSame('realbox@yandex.test', $r->defaultFromAddress());
    }

    public function test_default_from_uses_smtp_user_when_forced_regardless_of_platform_setting(): void
    {
        PlatformSetting::set('email.default_from_address', 'other@example.test', 'string');
        config([
            'mail.from.address' => 'fallback@example.test',
            'mail.mailers.smtp.username' => 'yandexuser@yandex.test',
            'mail.use_smtp_user_as_platform_from' => true,
        ]);

        $r = new ProductMailSettingsResolver;

        $this->assertSame('yandexuser@yandex.test', $r->defaultFromAddress());
    }
}
