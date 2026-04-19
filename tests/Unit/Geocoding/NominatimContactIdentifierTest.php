<?php

declare(strict_types=1);

namespace Tests\Unit\Geocoding;

use App\Support\NominatimContactIdentifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NominatimContactIdentifierTest extends TestCase
{
    public function test_explicit_contact_wins(): void
    {
        $this->assertSame(
            'explicit@company.example',
            NominatimContactIdentifier::resolve('explicit@company.example', 'hello@example.com', 'http://localhost'),
        );
    }

    #[DataProvider('disallowedMailFallbackProvider')]
    public function test_disallowed_mail_falls_back_to_geocoding_host(string $mail, string $appUrl, string $expected): void
    {
        $this->assertSame(
            $expected,
            NominatimContactIdentifier::resolve(null, $mail, $appUrl),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function disallowedMailFallbackProvider(): array
    {
        return [
            'example.com' => ['hello@example.com', 'http://rentbase.local', 'geocoding@rentbase.local'],
            'example.org' => ['a@example.org', 'https://app.test', 'geocoding@app.test'],
            'example.net' => ['a@example.net', 'http://x.localhost', 'geocoding@x.localhost'],
        ];
    }

    public function test_valid_mail_from_is_used_when_no_explicit(): void
    {
        $this->assertSame(
            'ops@rentbase.su',
            NominatimContactIdentifier::resolve(null, 'ops@rentbase.su', 'http://localhost'),
        );
    }

    public function test_empty_explicit_string_falls_through(): void
    {
        $this->assertSame(
            'geocoding@my.app',
            NominatimContactIdentifier::resolve('   ', 'hello@example.com', 'https://my.app/path'),
        );
    }
}
