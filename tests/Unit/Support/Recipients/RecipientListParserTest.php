<?php

namespace Tests\Unit\Support\Recipients;

use App\Support\Recipients\RecipientListParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipientListParserTest extends TestCase
{
    public function test_empty_and_null(): void
    {
        $this->assertSame([], RecipientListParser::parse(null));
        $this->assertSame([], RecipientListParser::parse(''));
        $this->assertSame([], RecipientListParser::parse('   '));
    }

    public function test_csv_trims_and_drops_empty_and_unique(): void
    {
        $this->assertSame(
            ['111', '-1001234567890'],
            RecipientListParser::parse(' 111 , , 111 , -1001234567890 ')
        );
    }

    public function test_json_array(): void
    {
        $this->assertSame(
            ['a', 'b'],
            RecipientListParser::parse('["a", "b", "a"]')
        );
    }

    #[DataProvider('plainCsvProvider')]
    public function test_plain_csv(string $input, array $expected): void
    {
        $this->assertSame($expected, RecipientListParser::parse($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function plainCsvProvider(): iterable
    {
        yield 'simple' => ['1,2,3', ['1', '2', '3']];

        yield 'negative_group' => ['-100123', ['-100123']];
    }
}
