<?php

namespace Tests\Unit;

use App\Support\RussianPhone;
use PHPUnit\Framework\TestCase;

class RussianPhoneTest extends TestCase
{
    public function test_accepts_masked_russian_mobile(): void
    {
        $this->assertTrue(RussianPhone::isValid('+7 (913) 060-86-89'));
        $this->assertSame('+79130608689', RussianPhone::normalize('+7 (913) 060-86-89'));
    }

    public function test_normalizes_leading_eight(): void
    {
        $this->assertSame('+79130608689', RussianPhone::normalize('8 (913) 060-86-89'));
    }

    public function test_to_masked_from_e164(): void
    {
        $this->assertSame('+7 (913) 060-86-89', RussianPhone::toMasked('+79130608689'));
    }

    public function test_filament_tel_display_regex_accepts_masked_ru(): void
    {
        $rx = RussianPhone::filamentTelDisplayRegex();
        $this->assertSame(1, preg_match($rx, ''));
        $this->assertSame(1, preg_match($rx, '+7 (913) 060-86-89'));
        $this->assertSame(1, preg_match($rx, '+44 20 7946 0958'));
        $this->assertSame(0, preg_match($rx, 'call me'));
    }
}
