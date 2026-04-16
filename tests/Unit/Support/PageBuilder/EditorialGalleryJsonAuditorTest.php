<?php

namespace Tests\Unit\Support\PageBuilder;

use App\Support\PageBuilder\EditorialGalleryJsonAuditor;
use PHPUnit\Framework\TestCase;

final class EditorialGalleryJsonAuditorTest extends TestCase
{
    public function test_detects_bad_rows(): void
    {
        $issues = EditorialGalleryJsonAuditor::collectIssues([
            'items' => [
                [
                    'image_url' => 'https://news.example.com/post/hello',
                    'video_url' => 'https://vk.com/video-1_2',
                    'poster_url' => '<iframe></iframe>',
                    'caption' => 'Say &quot;hi&quot;',
                ],
            ],
        ]);
        $this->assertGreaterThanOrEqual(4, count($issues));
        $messages = array_column($issues, 'message');
        $this->assertTrue($this->containsSubstring($messages, 'HTML'));
        $this->assertTrue($this->containsSubstring($messages, 'VK'));
        $this->assertTrue($this->containsSubstring($messages, 'iframe'));
        $this->assertTrue($this->containsSubstring($messages, 'сущност'));
    }

    public function test_clean_payload(): void
    {
        $issues = EditorialGalleryJsonAuditor::collectIssues([
            'items' => [
                ['media_kind' => 'image', 'image_url' => 'site/brand/a.jpg', 'caption' => 'OK'],
            ],
        ]);
        $this->assertSame([], $issues);
    }

    public function test_external_article_issues(): void
    {
        $issues = EditorialGalleryJsonAuditor::collectIssues([
            'items' => [
                [
                    'media_kind' => 'external_article',
                    'article_url' => '',
                    'article_title' => '',
                    'article_fetched_title' => '',
                ],
            ],
        ]);
        $messages = array_column($issues, 'message');
        $this->assertTrue($this->containsSubstring($messages, 'article_url'));
        $this->assertTrue($this->containsSubstring($messages, 'заголовка'));
    }

    /**
     * @param  list<string>  $haystacks
     */
    private function containsSubstring(array $haystacks, string $needle): bool
    {
        foreach ($haystacks as $h) {
            if (str_contains($h, $needle)) {
                return true;
            }
        }

        return false;
    }
}
