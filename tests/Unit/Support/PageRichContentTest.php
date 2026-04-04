<?php

namespace Tests\Unit\Support;

use App\Support\PageRichContent;
use Tests\TestCase;

class PageRichContentTest extends TestCase
{
    public function test_passes_through_html_string(): void
    {
        $html = '<p>Hello</p><table><tr><td>x</td></tr></table>';
        $this->assertSame($html, PageRichContent::toHtml($html));
    }

    public function test_renders_tiptap_json_document_to_html_table(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'table',
                    'content' => [
                        [
                            'type' => 'tableRow',
                            'content' => [
                                [
                                    'type' => 'tableHeader',
                                    'content' => [
                                        [
                                            'type' => 'paragraph',
                                            'content' => [['type' => 'text', 'text' => 'Col A']],
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'tableCell',
                                    'content' => [
                                        [
                                            'type' => 'paragraph',
                                            'content' => [['type' => 'text', 'text' => 'Col B']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = PageRichContent::toHtml($doc);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Col A', $html);
        $this->assertStringContainsString('Col B', $html);
    }

    public function test_decodes_json_string_document(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Hi']],
                ],
            ],
        ];
        $html = PageRichContent::toHtml(json_encode($doc, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('Hi', $html);
        $this->assertStringContainsString('<p', $html);
    }

    public function test_plain_text_excerpt_strips_markup(): void
    {
        $html = '<p>Hello <strong>world</strong></p>';
        $this->assertSame('Hello world', PageRichContent::toPlainTextExcerpt($html, 100));
    }
}
