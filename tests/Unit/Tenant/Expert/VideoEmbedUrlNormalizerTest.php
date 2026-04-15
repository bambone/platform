<?php

namespace Tests\Unit\Tenant\Expert;

use App\Tenant\Expert\VideoEmbedUrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class VideoEmbedUrlNormalizerTest extends TestCase
{
    #[DataProvider('youtubeProvider')]
    public function test_youtube(string $share): void
    {
        $src = VideoEmbedUrlNormalizer::toIframeSrc('youtube', $share);
        $this->assertNotNull($src);
        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $src);
    }

    public function test_youtube_accepts_iframe_paste(): void
    {
        $html = '<iframe src="https://www.youtube.com/watch?v=dQw4w9WgXcQ"></iframe>';
        $src = VideoEmbedUrlNormalizer::toIframeSrc('youtube', $html);
        $this->assertNotNull($src);
        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $src);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function youtubeProvider(): iterable
    {
        yield 'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'];
        yield 'short' => ['https://youtu.be/dQw4w9WgXcQ'];
        yield 'shorts_path' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'];
        yield 'embed_path' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'];
        yield 'live_path' => ['https://www.youtube.com/live/dQw4w9WgXcQ'];
    }

    public function test_vk_page_url(): void
    {
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', 'https://vk.com/video-231646483_456239036');
        $this->assertNotNull($src);
        $this->assertStringContainsString('https://vk.com/video_ext.php', $src);
        $this->assertStringContainsString('oid=', $src);
        $this->assertStringContainsString('id=', $src);
    }

    public function test_vk_video_ext_passthrough(): void
    {
        $url = 'https://vk.com/video_ext.php?oid=-231646483&id=456239036';
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', $url);
        $this->assertNotNull($src);
        $this->assertStringContainsString('https://vk.com/video_ext.php', $src);
        $this->assertStringContainsString('oid=-231646483', $src);
        $this->assertStringContainsString('id=456239036', $src);
        $this->assertStringContainsString('hd=2', $src);
    }

    public function test_vk_video_ext_on_vkvideo_host_keeps_vkvideo_in_iframe(): void
    {
        $url = 'https://vkvideo.ru/video_ext.php?oid=-1&id=2&hash=ab';
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', $url);
        $this->assertNotNull($src);
        $this->assertSame($url, $src);
    }

    public function test_vk_video_ext_with_realistic_hash_does_not_append_hd(): void
    {
        $url = 'https://vkvideo.ru/video_ext.php?oid=-231646483&id=456239036&hash=0b8e847a6291fad3';
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', $url);
        $this->assertNotNull($src);
        $this->assertSame($url, $src);
    }

    public function test_vk_video_ext_does_not_duplicate_hd(): void
    {
        $url = 'https://vk.com/video_ext.php?oid=-1&id=2&hash=x&hd=1';
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', $url);
        $this->assertNotNull($src);
        $this->assertMatchesRegularExpression('/(^|&)hd=1(&|$)/', (string) parse_url((string) $src, PHP_URL_QUERY));
    }

    public function test_vk_vkvideo_host_page_url(): void
    {
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', 'https://vkvideo.ru/video-231646483_456239036');
        $this->assertNotNull($src);
        $this->assertStringContainsString('https://vkvideo.ru/video_ext.php', $src);
    }

    public function test_rejects_raw_iframe_html(): void
    {
        $this->assertNull(VideoEmbedUrlNormalizer::toIframeSrc('vk', '<iframe src="https://vk.com/"></iframe>'));
    }

    public function test_vk_accepts_iframe_paste_with_video_src(): void
    {
        $html = '<iframe src="https://vk.com/video-231646483_456239036" width="640"></iframe>';
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', $html);
        $this->assertNotNull($src);
        $this->assertStringContainsString('video_ext.php', $src);
    }

    public function test_extract_share_url_from_iframe_paste(): void
    {
        $this->assertSame(
            'https://vk.com/video_ext.php?oid=-1&id=2&hash=abc',
            VideoEmbedUrlNormalizer::extractShareUrlFromPaste(
                '<iframe allowfullscreen src="https://vk.com/video_ext.php?oid=-1&id=2&hash=abc"></iframe>',
            ),
        );
    }

    public function test_vk_embed_probably_missing_hash_for_short_video_link(): void
    {
        $this->assertTrue(VideoEmbedUrlNormalizer::vkEmbedProbablyMissingHash('https://vk.com/video-231646483_456239036'));
        $this->assertTrue(VideoEmbedUrlNormalizer::vkEmbedProbablyMissingHash(
            'https://vkvideo.ru/video-231646483_456239036',
        ));
        $this->assertFalse(VideoEmbedUrlNormalizer::vkEmbedProbablyMissingHash(
            'https://vk.com/video_ext.php?oid=-1&id=2&hash=deadbeef',
        ));
        $this->assertFalse(VideoEmbedUrlNormalizer::vkEmbedProbablyMissingHash(
            'https://vkvideo.ru/video_ext.php?oid=-231646483&id=456239036&hash=0b8e847a6291fad3',
        ));
    }

    public function test_unknown_provider(): void
    {
        $this->assertNull(VideoEmbedUrlNormalizer::toIframeSrc('vimeo', 'https://vimeo.com/123'));
    }

    public function test_normalize_vk_share_url_for_storage_rewrites_watch_page(): void
    {
        $this->assertSame(
            'https://vkvideo.ru/video-231646483_456239036',
            VideoEmbedUrlNormalizer::normalizeVkShareUrlForStorage('https://vk.com/video-231646483_456239036'),
        );
        $this->assertSame(
            'https://vkvideo.ru/video-231646483_456239036',
            VideoEmbedUrlNormalizer::normalizeVkShareUrlForStorage('http://www.vk.com/video-231646483_456239036'),
        );
        $this->assertSame(
            'https://vkvideo.ru/video-1_2',
            VideoEmbedUrlNormalizer::normalizeVkShareUrlForStorage('https://m.vk.com/video-1_2'),
        );
    }

    public function test_normalize_vk_share_url_for_storage_does_not_rewrite_video_ext(): void
    {
        $in = 'https://vk.com/video_ext.php?oid=-231646483&id=456239036&hd=2&hash=abc';
        $this->assertSame($in, VideoEmbedUrlNormalizer::normalizeVkShareUrlForStorage($in));
    }

    public function test_normalize_vk_share_url_for_storage_leaves_other_vk_pages(): void
    {
        $club = 'https://vk.com/club123';
        $this->assertSame($club, VideoEmbedUrlNormalizer::normalizeVkShareUrlForStorage($club));
    }

    public function test_normalize_vk_share_url_for_storage_leaves_vkvideo_host(): void
    {
        $u = 'https://vkvideo.ru/video-1_2';
        $this->assertSame($u, VideoEmbedUrlNormalizer::normalizeVkShareUrlForStorage($u));
    }

    public function test_vk_rejects_non_vk_host(): void
    {
        $this->assertNull(VideoEmbedUrlNormalizer::toIframeSrc('vk', 'https://evil.test/https://vk.com/video-1_2'));
    }

    public function test_vk_accepts_www_host(): void
    {
        $src = VideoEmbedUrlNormalizer::toIframeSrc('vk', 'https://www.vk.com/video-1_2');
        $this->assertNotNull($src);
        $this->assertStringContainsString('https://vk.com/video_ext.php', $src);
    }
}
