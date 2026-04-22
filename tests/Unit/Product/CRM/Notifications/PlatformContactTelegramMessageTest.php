<?php

namespace Tests\Unit\Product\CRM\Notifications;

use App\Models\CrmRequest;
use App\Product\CRM\Notifications\PlatformContactTelegramMessage;
use Tests\TestCase;

class PlatformContactTelegramMessageTest extends TestCase
{
    public function test_omits_message_section_when_message_empty(): void
    {
        $crm = new CrmRequest([
            'id' => 99,
            'request_type' => 'platform_contact',
            'name' => 'N',
            'phone' => '+1',
            'message' => '',
        ]);

        $text = PlatformContactTelegramMessage::build($crm);

        $this->assertStringNotContainsString('Сообщение:', $text);
    }

    public function test_omits_utm_block_when_all_empty(): void
    {
        $crm = new CrmRequest([
            'id' => 1,
            'request_type' => 'platform_contact',
            'name' => 'N',
            'phone' => '+1',
            'utm_source' => null,
            'utm_medium' => '',
        ]);

        $text = PlatformContactTelegramMessage::build($crm);

        $this->assertStringNotContainsString('UTM', $text);
    }
}
