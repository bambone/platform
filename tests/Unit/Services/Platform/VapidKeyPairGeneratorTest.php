<?php

namespace Tests\Unit\Services\Platform;

use App\Services\Platform\VapidKeyPairGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(VapidKeyPairGenerator::class)]
class VapidKeyPairGeneratorTest extends TestCase
{
    public function test_generates_base64url_pair_with_expected_shapes(): void
    {
        $gen = new VapidKeyPairGenerator;
        $pair = $gen->generate();

        $this->assertArrayHasKey('public', $pair);
        $this->assertArrayHasKey('private', $pair);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $pair['public']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $pair['private']);

        $pubBin = $this->base64UrlDecode($pair['public']);
        $privBin = $this->base64UrlDecode($pair['private']);

        $this->assertSame(65, strlen($pubBin));
        $this->assertSame("\x04", $pubBin[0]);
        $this->assertSame(32, strlen($privBin));
    }

    public function test_successive_generations_differ(): void
    {
        $gen = new VapidKeyPairGenerator;
        $a = $gen->generate();
        $b = $gen->generate();

        $this->assertNotSame($a['public'], $b['public']);
        $this->assertNotSame($a['private'], $b['private']);
    }

    private function base64UrlDecode(string $b64url): string
    {
        $pad = 4 - (strlen($b64url) % 4);
        if ($pad < 4) {
            $b64url .= str_repeat('=', $pad);
        }

        $standard = strtr($b64url, '-_', '+/');
        $decoded = base64_decode($standard, true);
        $this->assertIsString($decoded);

        return $decoded;
    }
}
