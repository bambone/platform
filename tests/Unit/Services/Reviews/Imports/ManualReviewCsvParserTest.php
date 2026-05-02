<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Reviews\Imports;

use App\Services\Reviews\Imports\ManualReviewCsvParser;
use PHPUnit\Framework\TestCase;

final class ManualReviewCsvParserTest extends TestCase
{
    private ManualReviewCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ManualReviewCsvParser;
    }

    public function test_comma_delimiter(): void
    {
        $raw = <<<'CSV'
author_name,body,rating
Иван,"Ок,",5
CSV;
        $r = $this->parser->parse($raw);
        $this->assertTrue($r->isOk());
        $this->assertCount(1, $r->rows);
        $this->assertSame('Ок,', (string) $r->rows[0]['body']);
    }

    public function test_semicolon_delimiter(): void
    {
        $raw = "author_name;body;rating\nИван;Норм;4\n";
        $r = $this->parser->parse($raw);
        $this->assertTrue($r->isOk());
        $this->assertCount(1, $r->rows);
        $this->assertSame('Норм', (string) $r->rows[0]['body']);
    }

    public function test_utf8_bom_stripped(): void
    {
        $raw = "\xEF\xBB\xBFauthor_name,body,rating\nx,y,1\n";
        $r = $this->parser->parse($raw);
        $this->assertTrue($r->isOk());
        $this->assertArrayHasKey('body', $r->rows[0]);
        $this->assertSame('y', (string) $r->rows[0]['body']);
    }

    public function test_quoted_body_with_newline(): void
    {
        $raw = <<<'CSV'
author_name;body;rating
Иван;"Первая строка
Вторая строка";5
CSV;
        $r = $this->parser->parse($raw);
        $this->assertTrue($r->isOk());
        $this->assertCount(1, $r->rows);
        $this->assertStringContainsString("Первая строка\nВторая строка", (string) $r->rows[0]['body']);
    }

    public function test_quoted_body_with_semicolon_inside(): void
    {
        $raw = <<<'CSV'
author_name;body;rating
Иван;"a;b";5
CSV;
        $r = $this->parser->parse($raw);
        $this->assertTrue($r->isOk());
        $this->assertSame('a;b', (string) $r->rows[0]['body']);
    }

    public function test_empty_body_row_skipped(): void
    {
        $raw = "author_name,body,rating\nA,,1\nB,text,5\n";
        $r = $this->parser->parse($raw);
        $this->assertTrue($r->isOk());
        $this->assertCount(1, $r->rows);
        $this->assertSame('B', (string) $r->rows[0]['author_name']);
    }

    public function test_header_body_column_with_spaces_and_mixed_case(): void
    {
        $raw = <<<'CSV'
 AUTHOR Name , BODY ,rating
alice,Строка с достаточной длиной для импортного минимума здесь есть.,5
CSV;
        $r = $this->parser->parse($raw);
        $this->assertTrue($r->isOk());
        $this->assertCount(1, $r->rows);
        $this->assertSame('alice', (string) $r->rows[0]['author_name']);
        $this->assertStringContainsString('длиной для импортного минимума', (string) $r->rows[0]['body']);
    }

    public function test_missing_body_header_is_error(): void
    {
        $raw = "author_name,text,rating\nA,hello,5\n";
        $r = $this->parser->parse($raw);
        $this->assertFalse($r->isOk());
        $this->assertNotEmpty($r->errors);
    }
}
