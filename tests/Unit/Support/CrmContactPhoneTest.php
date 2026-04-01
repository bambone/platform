<?php

namespace Tests\Unit\Support;

use App\Support\CrmContactPhone;
use PHPUnit\Framework\TestCase;

class CrmContactPhoneTest extends TestCase
{
    public function test_formats_valid_russian_mobile(): void
    {
        $this->assertSame('+7 (999) 777-66-55', CrmContactPhone::display('+79997776655'));
        $this->assertSame('+79997776655', CrmContactPhone::telHref('+79997776655'));
    }

    public function test_absurd_length_has_no_tel_href_and_truncated_display(): void
    {
        $raw = '+'.str_repeat('9', 38);
        $this->assertNull(CrmContactPhone::telHref($raw));
        $display = CrmContactPhone::display($raw);
        $this->assertSame(36, mb_strlen($display));
        $this->assertStringEndsWith('…', $display);
    }

    public function test_sixteen_digit_russian_looking_garbage_has_no_tel_href(): void
    {
        $raw = '+7999999999999999';
        $this->assertNull(CrmContactPhone::telHref($raw));
        $this->assertSame($raw, CrmContactPhone::display($raw));
    }

    public function test_empty_returns_empty_display_and_null_href(): void
    {
        $this->assertSame('', CrmContactPhone::display(null));
        $this->assertSame('', CrmContactPhone::display('   '));
        $this->assertNull(CrmContactPhone::telHref(null));
    }
}
